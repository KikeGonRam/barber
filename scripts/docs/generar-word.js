// Genera el .docx de cada manual de docs/manuales/ a partir de su .md, con el diseño de los
// entregables del equipo (Bitácora y Word de Infraestructura): Arial, carta, negro + oro,
// portada con barra negra, logo y mascotas, filete dorado en los capítulos y tablas con
// cabecera gris.
//
// Uso (desde scripts/docs):  npm install   y luego   node generar-word.js [carpeta-del-manual]
//
// Formato del .md:  "# Título", un párrafo de subtítulo, una tabla Dato|Valor (portada) y
// capítulos "## N. Nombre". "###" y "####" son subtítulos. Un blockquote que empieza con
// **Nota**, **Aviso**, **Importante**, **Riesgo** o **Hecho** se dibuja como recuadro de color.
const fs = require("fs");
const path = require("path");
const { marked } = require("marked");
const {
  Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell, ImageRun, Footer, AlignmentType,
  BorderStyle, WidthType, ShadingType, VerticalAlign, HeadingLevel, PageNumber, LevelFormat,
  TableLayoutType, TableOfContents, PageBreak,
} = require("docx");

const RES = path.join(__dirname, "recursos");
const MANUALES = path.join(__dirname, "..", "..", "docs", "manuales");

// ---- Identidad (la misma paleta de la bitácora y del Word de infraestructura)
const BLACK = "1F1F1F", GOLD = "C9A227", HEAD = "4A4A4A", LINE = "BFBFBF", MUTED = "595959", DARKGOLD = "8A6D12";
const COLORS = { ok: "2E7D32", warn: "B26A00", bad: "8C2F2F", info: "1D4ED8", gold: GOLD };
const SOFT = { ok: "E8F3E9", warn: "FBF0DF", bad: "F6E4E4", info: "E6ECFA", gold: "F7F0DA" };
const FONT = "Arial", MONO = "Consolas";
const W = 10080; // ancho útil: carta con márgenes de 0.75"

const border = { style: BorderStyle.SINGLE, size: 4, color: LINE };
const borders = { top: border, bottom: border, left: border, right: border };
const noBorder = { style: BorderStyle.NONE, size: 0, color: "FFFFFF" };
const noBorders = { top: noBorder, bottom: noBorder, left: noBorder, right: noBorder };
const img = (f) => fs.readFileSync(path.join(RES, f));

// ---- Texto en línea a partir de los tokens de marked
function inline(tokens, o = {}) {
  const size = o.size || 20;
  const out = [];
  const walk = (list, st) => {
    for (const t of list || []) {
      switch (t.type) {
        case "strong": walk(t.tokens, { ...st, bold: true }); break;
        case "em": walk(t.tokens, { ...st, italics: true }); break;
        case "del": walk(t.tokens, st); break;
        case "codespan":
          out.push(new TextRun({ text: t.text, font: MONO, size: size - 2, bold: st.bold, color: "7A5A00" })); break;
        case "link": {
          walk(t.tokens, { ...st, color: "1D4ED8" });
          if (/^https?:/.test(t.href) && t.text !== t.href) out.push(new TextRun({ text: ` (${t.href})`, font: FONT, size: size - 4, color: MUTED }));
          break;
        }
        case "image": out.push(new TextRun({ text: t.text || "", font: FONT, size, italics: true, color: MUTED })); break;
        case "br": out.push(new TextRun({ break: 1 })); break;
        case "escape": case "text":
          if (t.tokens && t.tokens.length) walk(t.tokens, st);
          else out.push(new TextRun({ text: decode(t.text), font: FONT, size, bold: st.bold || o.bold, italics: st.italics || o.italics, color: st.color || o.color || BLACK }));
          break;
        default: if (t.raw) out.push(new TextRun({ text: decode(t.raw), font: FONT, size, color: BLACK }));
      }
    }
  };
  walk(tokens, {});
  return out;
}
const decode = (s) => String(s).replace(/&amp;/g, "&").replace(/&lt;/g, "<").replace(/&gt;/g, ">").replace(/&quot;/g, '"').replace(/&#39;/g, "'");
const plain = (tokens) => (tokens || []).map((t) => (t.tokens ? plain(t.tokens) : decode(t.text || t.raw || ""))).join("");

// ---- Bloques
const para = (tokens, o = {}) => new Paragraph({ children: inline(tokens, o), alignment: o.align, spacing: { before: o.before ?? 0, after: o.after ?? 100, line: 276 }, keepNext: o.keepNext });
const gap = (after = 160) => new Paragraph({ spacing: { after }, children: [] });
const h1 = (t) => new Paragraph({ heading: HeadingLevel.HEADING_1, keepNext: true, pageBreakBefore: true, spacing: { before: 0, after: 160 },
  border: { bottom: { style: BorderStyle.SINGLE, size: 12, color: GOLD, space: 4 } }, children: [new TextRun({ text: t, font: FONT, size: 32, bold: true, color: BLACK })] });
const h2 = (t) => new Paragraph({ heading: HeadingLevel.HEADING_2, keepNext: true, spacing: { before: 260, after: 110 }, children: [new TextRun({ text: t, font: FONT, size: 25, bold: true, color: HEAD })] });
const h3 = (t) => new Paragraph({ heading: HeadingLevel.HEADING_3, keepNext: true, spacing: { before: 180, after: 80 }, children: [new TextRun({ text: t, font: FONT, size: 21, bold: true, color: DARKGOLD })] });

let numInstance = 0;
function list(token, level = 0) {
  const out = [];
  if (token.ordered) numInstance += 1;
  const inst = numInstance;
  for (const item of token.items) {
    const own = item.tokens.filter((t) => t.type !== "list");
    const text = own.flatMap((t) => (t.type === "text" || t.type === "paragraph" ? t.tokens || [{ type: "text", text: t.text }] : []));
    out.push(new Paragraph({
      numbering: token.ordered ? { reference: "num", level: 0, instance: inst } : { reference: "bul", level: Math.min(level, 1) },
      children: inline(text), spacing: { after: 60, line: 264 },
    }));
    for (const sub of item.tokens.filter((t) => t.type === "list")) out.push(...list(sub, level + 1));
  }
  return out;
}

function callout(kind, title, paragraphs) {
  const color = COLORS[kind];
  const fmt = { shading: { fill: SOFT[kind], type: ShadingType.CLEAR, color: "auto" }, border: { left: { style: BorderStyle.SINGLE, size: 24, color, space: 8 } }, indent: { left: 160, right: 120 } };
  return [
    new Paragraph({ ...fmt, keepNext: true, spacing: { before: 120, after: 40 }, children: [new TextRun({ text: title, font: FONT, size: 20, bold: true, color })] }),
    ...paragraphs.map((tokens, i) => new Paragraph({ ...fmt, keepNext: i < paragraphs.length - 1, spacing: { after: i === paragraphs.length - 1 ? 160 : 60, line: 264 }, children: inline(tokens, { size: 19 }) })),
  ];
}
const KINDS = { nota: "info", aviso: "warn", importante: "gold", riesgo: "bad", hecho: "ok" };
function quote(token) {
  const first = token.tokens.find((t) => t.type === "paragraph");
  const lead = first && first.tokens[0];
  if (lead && lead.type === "strong") {
    const word = plain(lead.tokens).toLowerCase().replace(/[:.]/g, "");
    if (KINDS[word]) {
      const paras = [];
      first.tokens.shift();
      if (first.tokens[0] && first.tokens[0].type === "text") first.tokens[0].text = first.tokens[0].text.replace(/^[:.\s]+/, "");
      if (plain(first.tokens).trim()) paras.push(first.tokens);
      for (const t of token.tokens) if (t !== first && t.type === "paragraph") paras.push(t.tokens);
      return callout(KINDS[word], plain(lead.tokens).replace(/[:.]$/, ""), paras);
    }
  }
  return callout("gold", "", token.tokens.filter((t) => t.type === "paragraph").map((t) => t.tokens));
}

const cell = (tokens, w, o = {}) => new TableCell({
  width: { size: w, type: WidthType.DXA }, borders, verticalAlign: VerticalAlign.TOP,
  shading: o.fill ? { fill: o.fill, type: ShadingType.CLEAR, color: "auto" } : undefined,
  margins: { top: 60, bottom: 60, left: 90, right: 90 },
  children: [new Paragraph({ spacing: { after: 20, line: 250 }, children: inline(tokens, { size: 17, bold: o.bold, color: o.color }) })],
});
function table(token, totalWidth = W) {
  const cols = token.header.length;
  const weight = Array.from({ length: cols }, (_, c) => {
    const lens = [plain(token.header[c].tokens), ...token.rows.map((r) => plain(r[c].tokens))].map((s) => s.length);
    return Math.min(Math.max(Math.max(...lens) * 0.55 + Math.sqrt(Math.max(...lens)) * 2, 8), 60);
  });
  const sum = weight.reduce((a, b) => a + b, 0);
  const widths = weight.map((x) => Math.max(900, Math.round((x / sum) * totalWidth)));
  widths[widths.length - 1] += totalWidth - widths.reduce((a, b) => a + b, 0);
  return new Table({
    width: { size: totalWidth, type: WidthType.DXA }, columnWidths: widths, layout: TableLayoutType.FIXED,
    rows: [
      new TableRow({ tableHeader: true, cantSplit: true, children: token.header.map((h, i) => cell(h.tokens, widths[i], { fill: HEAD, bold: true, color: "FFFFFF" })) }),
      ...token.rows.map((r, ri) => new TableRow({ cantSplit: true, children: r.map((c, i) => cell(c.tokens, widths[i], { bold: i === 0 ? true : undefined, fill: ri % 2 === 1 ? "F7F4EC" : undefined })) })),
    ],
  });
}

function code(token) {
  return token.text.replace(/\s+$/, "").split("\n").map((line, i, all) => new Paragraph({
    shading: { fill: "F4F4F4", type: ShadingType.CLEAR, color: "auto" },
    border: { left: { style: BorderStyle.SINGLE, size: 18, color: LINE, space: 6 } },
    indent: { left: 140, right: 100 }, keepNext: i < all.length - 1, keepLines: true,
    spacing: { after: i === all.length - 1 ? 160 : 0, line: 240 },
    children: [new TextRun({ text: line.length ? line : " ", font: MONO, size: 16, color: BLACK })],
  }));
}

// Dimensiones de un JPEG: se busca el marcador SOF (0xFFC0 a 0xFFC3) y se leen alto y ancho.
function jpgSize(buf) {
  let i = 2;
  while (i + 9 < buf.length) {
    if (buf[i] !== 0xff) { i += 1; continue; }
    const marker = buf[i + 1];
    if (marker >= 0xc0 && marker <= 0xc3) return { ih: buf.readUInt16BE(i + 5), iw: buf.readUInt16BE(i + 7) };
    i += 2 + buf.readUInt16BE(i + 2);
  }
  return null;
}

function picture(token, baseDir) {
  const file = path.resolve(baseDir, decodeURIComponent(token.href));
  if (!fs.existsSync(file)) return [para([{ type: "text", text: `[Imagen no encontrada: ${token.href}]` }], { italics: true, color: MUTED })];
  const ext = path.extname(file).slice(1).toLowerCase().replace("jpeg", "jpg");
  const data = fs.readFileSync(file);
  // Tamaño real desde la cabecera (PNG o JPG); el ancho máximo es 560 px y las capturas altas se limitan en alto.
  let w = 560, h = 315;
  const dim = ext === "png" ? { iw: data.readUInt32BE(16), ih: data.readUInt32BE(20) } : jpgSize(data);
  if (dim) {
    const f = Math.min(1, 560 / dim.iw, ext === "jpg" && dim.ih > dim.iw ? 640 / dim.ih : 1);
    w = Math.round(dim.iw * f); h = Math.round(dim.ih * f);
  }
  return [
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 80, after: 60 }, keepNext: true,
      children: [new ImageRun({ type: ext, data, transformation: { width: w, height: h }, altText: { title: token.text || "Imagen", description: token.text || "Imagen", name: path.basename(file) } })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 200 }, children: [new TextRun({ text: token.text || "", font: FONT, size: 16, italics: true, color: MUTED })] }),
  ];
}

// ---- Portada
function cover(title, subtitle, metaToken, footerName) {
  const bar = new Table({
    width: { size: W, type: WidthType.DXA }, columnWidths: [1300, W - 1300], layout: TableLayoutType.FIXED,
    rows: [new TableRow({ children: [
      new TableCell({ width: { size: 1300, type: WidthType.DXA }, borders: noBorders, verticalAlign: VerticalAlign.CENTER, shading: { fill: BLACK, type: ShadingType.CLEAR, color: "auto" }, margins: { top: 140, bottom: 140, left: 160, right: 60 },
        children: [new Paragraph({ children: [new ImageRun({ type: "png", data: img("urbanblade-mark.png"), transformation: { width: 62, height: 62 }, altText: { title: "UrbanBlade", description: "Logotipo de UrbanBlade", name: "logo" } })] })] }),
      new TableCell({ width: { size: W - 1300, type: WidthType.DXA }, borders: noBorders, verticalAlign: VerticalAlign.CENTER, shading: { fill: BLACK, type: ShadingType.CLEAR, color: "auto" }, margins: { top: 140, bottom: 140, left: 120, right: 160 },
        children: [
          new Paragraph({ spacing: { after: 30 }, children: [new TextRun({ text: `URBAN BLADE  —  ${title.toUpperCase()}`, font: FONT, size: 30, bold: true, color: "FFFFFF" })] }),
          new Paragraph({ children: [new TextRun({ text: "Sistema de gestión de citas para barbería", font: FONT, size: 20, color: GOLD })] }),
        ] }),
    ] })],
  });
  const card = (file, name, role, text) => new TableCell({ width: { size: W / 3, type: WidthType.DXA }, borders: noBorders, margins: { top: 60, bottom: 60, left: 80, right: 80 }, children: [
    new Paragraph({ alignment: AlignmentType.CENTER, children: [new ImageRun({ type: "png", data: img(file), transformation: { width: 130, height: 195 }, altText: { title: name, description: `${name}, ${role}`, name } })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 60, after: 20 }, children: [new TextRun({ text: name, font: FONT, size: 22, bold: true, color: BLACK })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 30 }, children: [new TextRun({ text: role, font: FONT, size: 16, bold: true, color: DARKGOLD })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, children: [new TextRun({ text, font: FONT, size: 15, color: MUTED })] }),
  ] });
  const mascots = new Table({ width: { size: W, type: WidthType.DXA }, columnWidths: [W / 3, W / 3, W / 3], layout: TableLayoutType.FIXED, rows: [new TableRow({ children: [
    card("bladebot.png", "Bladebot", "Asistente de precisión", "Acompaña las tareas del día a día."),
    card("nava-panther.png", "Nava", "Guía de experiencia", "Orienta y explica el camino."),
    card("bruno-raven.png", "Bruno", "Guardián del conocimiento", "Cuida los datos y la seguridad."),
  ] })] });
  return [
    bar, gap(300),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 80 }, children: [new TextRun({ text: title, font: FONT, size: 52, bold: true, color: BLACK })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 360 }, children: inline(subtitle, { size: 24, color: MUTED }) }),
    mascots, gap(360),
    ...(metaToken ? [table(metaToken)] : []),
    new Paragraph({ children: [new PageBreak()] }),
    new Paragraph({ spacing: { after: 160 }, border: { bottom: { style: BorderStyle.SINGLE, size: 12, color: GOLD, space: 4 } }, children: [new TextRun({ text: "Índice", font: FONT, size: 32, bold: true, color: BLACK })] }),
    new TableOfContents("Índice", { hyperlink: true, headingStyleRange: "1-2" }),
    new Paragraph({ spacing: { before: 120 }, children: [new TextRun({ text: "Si el índice aparece vacío, en Word: clic derecho sobre él → Actualizar campos.", font: FONT, size: 16, color: MUTED })] }),
  ];
}

// ---- Documento completo
async function construir(carpeta) {
  const dir = path.join(MANUALES, carpeta);
  const mdFile = fs.readdirSync(dir).find((f) => f.endsWith(".md") && f.toLowerCase() !== "readme.md");
  if (!mdFile) throw new Error(`Sin .md en ${carpeta}`);
  const tokens = marked.lexer(fs.readFileSync(path.join(dir, mdFile), "utf8"));
  const title = plain(tokens.find((t) => t.type === "heading" && t.depth === 1).tokens);
  const idx = tokens.findIndex((t) => t.type === "heading" && t.depth === 1);
  const rest = tokens.slice(idx + 1).filter((t) => t.type !== "space");
  let subtitle = [{ type: "text", text: "" }];
  if (rest[0] && rest[0].type === "paragraph") subtitle = rest.shift().tokens;
  let meta = null;
  if (rest[0] && rest[0].type === "table") meta = rest.shift();

  const body = [];
  for (const t of rest) {
    switch (t.type) {
      case "heading":
        if (t.depth === 2) body.push(h1(plain(t.tokens)));
        else if (t.depth === 3) body.push(h2(plain(t.tokens)));
        else body.push(h3(plain(t.tokens)));
        break;
      case "paragraph": {
        const imgs = t.tokens.filter((x) => x.type === "image");
        if (imgs.length === 1 && t.tokens.length === 1) body.push(...picture(imgs[0], dir));
        else body.push(para(t.tokens));
        break;
      }
      case "list": body.push(...list(t), gap(60)); break;
      case "table": body.push(table(t), gap(140)); break;
      case "code": body.push(...code(t)); break;
      case "blockquote": body.push(...quote(t)); break;
      case "hr": break;
      default: break;
    }
  }

  const doc = new Document({
    creator: "Equipo UrbanBlade", title: `UrbanBlade — ${title}`, description: plain(subtitle),
    features: { updateFields: true },
    styles: {
      default: { document: { run: { font: FONT, size: 20 } } },
      paragraphStyles: [
        { id: "Heading1", name: "Heading 1", basedOn: "Normal", next: "Normal", quickFormat: true, run: { font: FONT, size: 32, bold: true }, paragraph: { outlineLevel: 0 } },
        { id: "Heading2", name: "Heading 2", basedOn: "Normal", next: "Normal", quickFormat: true, run: { font: FONT, size: 25, bold: true }, paragraph: { outlineLevel: 1 } },
        { id: "Heading3", name: "Heading 3", basedOn: "Normal", next: "Normal", quickFormat: true, run: { font: FONT, size: 21, bold: true }, paragraph: { outlineLevel: 2 } },
      ],
    },
    numbering: { config: [
      { reference: "bul", levels: [
        { level: 0, format: LevelFormat.BULLET, text: "•", alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 540, hanging: 270 } } } },
        { level: 1, format: LevelFormat.BULLET, text: "–", alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 1000, hanging: 270 } } } },
      ] },
      { reference: "num", levels: [{ level: 0, format: LevelFormat.DECIMAL, text: "%1.", alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 540, hanging: 360 } }, run: { bold: true, color: DARKGOLD } } }] },
    ] },
    sections: [{
      properties: { page: { size: { width: 12240, height: 15840 }, margin: { top: 900, right: 1080, bottom: 1000, left: 1080, header: 400, footer: 400 } } },
      footers: { default: new Footer({ children: [new Paragraph({
        border: { top: { style: BorderStyle.SINGLE, size: 8, color: GOLD, space: 4 } },
        children: [
          new TextRun({ text: `UrbanBlade · ${title} · página `, font: FONT, size: 16, color: MUTED }),
          new TextRun({ children: [PageNumber.CURRENT], font: FONT, size: 16, color: MUTED }),
        ],
      })] }) },
      children: [...cover(title, subtitle, meta), ...body],
    }],
  });
  const out = path.join(dir, mdFile.replace(/\.md$/, ".docx"));
  fs.writeFileSync(out, await Packer.toBuffer(doc));
  return out;
}

(async () => {
  const pedido = process.argv[2];
  const carpetas = fs.readdirSync(MANUALES, { withFileTypes: true }).filter((d) => d.isDirectory() && /^\d\d-/.test(d.name)).map((d) => d.name).filter((n) => !pedido || n === pedido);
  for (const c of carpetas) {
    const out = await construir(c);
    console.log("OK", path.relative(path.join(__dirname, "..", ".."), out), `${Math.round(fs.statSync(out).size / 1024)} KB`);
  }
})().catch((e) => { console.error(e); process.exit(1); });
