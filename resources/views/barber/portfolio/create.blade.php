<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Publicar <span class="text-gold">Nuevo Trabajo</span></h2>
                <p class="ui-subtitle">Muestra tu talento — sube fotos y videos de tu mejor obra.</p>
            </div>
            <a href="{{ route('barber.portfolio.index') }}" class="text-[10px] font-black uppercase tracking-widest text-white/30 hover:text-white transition self-start sm:self-auto">
                &larr; Volver
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('barber.portfolio.store') }}" enctype="multipart/form-data"
                  x-data="mediaUpload()" class="space-y-5">
                @csrf

                {{-- Título --}}
                <div class="rounded-2xl border border-white/[0.06] bg-[#111] p-5">
                    <div class="space-y-4">
                        <div>
                            <label class="ui-label" for="title">Título del trabajo</label>
                            <input id="title" name="title" value="{{ old('title') }}"
                                   class="ui-input !bg-black/30 border-white/10 text-white mt-1"
                                   placeholder="Ej: Fade Clásico con Diseño" required>
                            @error('title') <p class="mt-1 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label" for="description">Descripción <span class="text-white/20 normal-case font-normal">(opcional)</span></label>
                            <textarea id="description" name="description" rows="3"
                                      class="ui-input !bg-black/30 border-white/10 text-white leading-relaxed mt-1"
                                      placeholder="Describe la técnica, productos o estilo utilizado...">{{ old('description') }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Upload Zone --}}
                <div class="rounded-2xl border border-white/[0.06] bg-[#111] p-5">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-[9px] font-black uppercase tracking-[0.25em] text-white/30">
                            Fotos & Videos
                        </p>
                        <p class="text-[9px] font-bold text-white/30 uppercase tracking-wider"
                           x-text="`${files.length} / 10 archivos`"></p>
                    </div>

                    {{-- Drop zone --}}
                    <label
                        for="media-input"
                        class="relative flex flex-col items-center justify-center w-full rounded-2xl border-2 border-dashed cursor-pointer transition-all duration-200"
                        :class="dragging ? 'border-gold/60 bg-gold/5' : 'border-white/10 hover:border-gold/30 hover:bg-white/[0.02]'"
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="handleDrop($event)">

                        <div class="py-10 flex flex-col items-center gap-3">
                            <div class="h-14 w-14 rounded-2xl bg-gold/[0.06] border border-gold/15 flex items-center justify-center">
                                <svg class="h-7 w-7 text-gold/60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                            </div>
                            <div class="text-center">
                                <p class="text-sm font-black text-white uppercase tracking-tight">Arrastra o haz clic para subir</p>
                                <p class="text-[10px] text-white/30 font-medium mt-1">
                                    Fotos: JPG, PNG, WEBP &nbsp;·&nbsp; Videos: MP4, WEBM, MOV
                                </p>
                                <p class="text-[9px] text-white/20 font-bold uppercase mt-1">Máx. 10 archivos · 100 MB por archivo</p>
                            </div>
                        </div>

                        <input id="media-input" name="media[]" type="file" class="absolute inset-0 opacity-0 cursor-pointer"
                               multiple accept="image/jpeg,image/jpg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime,video/x-msvideo"
                               @change="handleFileSelect($event)" />
                    </label>

                    {{-- Previews grid --}}
                    <div x-show="files.length > 0" x-transition class="mt-4 grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2">
                        <template x-for="(file, index) in files" :key="index">
                            <div class="relative aspect-square rounded-xl overflow-hidden bg-black/40 group">
                                {{-- Image preview --}}
                                <img x-show="file.type === 'image'"
                                     :src="file.preview"
                                     class="h-full w-full object-cover" />

                                {{-- Video preview --}}
                                <div x-show="file.type === 'video'" class="h-full w-full flex flex-col items-center justify-center bg-black/60">
                                    <video :src="file.preview" class="h-full w-full object-cover absolute inset-0" muted playsinline></video>
                                    <div class="absolute inset-0 flex items-center justify-center bg-black/30">
                                        <div class="h-8 w-8 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center">
                                            <svg class="h-4 w-4 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M8 5v14l11-7z"/>
                                            </svg>
                                        </div>
                                    </div>
                                </div>

                                {{-- Position indicator (first = cover) --}}
                                <div x-show="index === 0"
                                     class="absolute top-1 left-1 px-1.5 py-0.5 rounded-md bg-gold text-black text-[7px] font-black uppercase">
                                    Portada
                                </div>

                                {{-- Video badge --}}
                                <div x-show="file.type === 'video' && index > 0"
                                     class="absolute top-1 left-1 px-1.5 py-0.5 rounded-md bg-purple-500/80 text-white text-[7px] font-black uppercase">
                                    Video
                                </div>

                                {{-- Remove button --}}
                                <button type="button"
                                        @click="removeFile(index)"
                                        class="absolute top-1 right-1 h-5 w-5 rounded-full bg-red-500/80 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </template>

                        {{-- Add more button --}}
                        <template x-if="files.length < 10">
                            <label for="media-input" class="aspect-square rounded-xl border-2 border-dashed border-white/10 flex items-center justify-center cursor-pointer hover:border-gold/30 transition-colors">
                                <svg class="h-6 w-6 text-white/20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                            </label>
                        </template>
                    </div>

                    @error('media')   <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                    @error('media.*') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                </div>

                {{-- Submit --}}
                <div class="flex justify-end gap-3">
                    <a href="{{ route('barber.portfolio.index') }}"
                       class="px-6 py-3 rounded-xl border border-white/10 text-[10px] font-black uppercase tracking-widest text-white/40 hover:text-white transition-all">
                        Cancelar
                    </a>
                    <button type="submit"
                            :disabled="files.length === 0"
                            :class="files.length === 0 ? 'opacity-40 cursor-not-allowed' : 'shadow-lg shadow-gold/20 hover:shadow-gold/30'"
                            class="ui-btn px-10 py-3 text-[10px] uppercase tracking-[0.2em] transition-all">
                        Publicar trabajo &rarr;
                    </button>
                </div>
            </form>
        </div>
    </div>

<script>
function mediaUpload() {
    return {
        files: [],
        dragging: false,
        fileInput: null,

        init() {
            this.fileInput = document.getElementById('media-input');
        },

        handleFileSelect(event) {
            this.addFiles(Array.from(event.target.files));
        },

        handleDrop(event) {
            this.dragging = false;
            this.addFiles(Array.from(event.dataTransfer.files));
        },

        addFiles(newFiles) {
            const allowed = [
                'image/jpeg','image/jpg','image/png','image/webp','image/gif',
                'video/mp4','video/webm','video/quicktime','video/x-msvideo','video/mpeg','video/ogg',
            ];
            for (const file of newFiles) {
                if (this.files.length >= 10) break;
                if (!allowed.includes(file.type)) continue;

                const isVideo = file.type.startsWith('video/');
                const preview = URL.createObjectURL(file);
                this.files.push({ file, preview, type: isVideo ? 'video' : 'image', name: file.name });
            }
            this.syncInputFiles();
        },

        removeFile(index) {
            URL.revokeObjectURL(this.files[index].preview);
            this.files.splice(index, 1);
            this.syncInputFiles();
        },

        syncInputFiles() {
            const dt = new DataTransfer();
            this.files.forEach(f => dt.items.add(f.file));
            this.fileInput.files = dt.files;
        },
    };
}
</script>
</x-app-layout>
