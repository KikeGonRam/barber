<x-guest-layout>
    <div class="mb-8 text-center">
        <h2 class="text-xl font-black uppercase tracking-widest text-white">Verificar <span class="text-gold italic font-serif tracking-normal lowercase">email</span></h2>
        <p class="mt-4 text-[10px] font-bold uppercase leading-relaxed tracking-widest text-muted">
            ¡Gracias por registrarte! Te enviamos un código de 6 dígitos a tu correo. Ingrésalo abajo para verificar tu cuenta. Si no lo recibiste, te enviamos otro con gusto.
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-6 rounded-xl border border-green-900/50 bg-green-950/20 p-4 text-center text-xs font-bold text-green-400">
            Te enviamos un nuevo código a la dirección de correo que proporcionaste durante el registro.
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-red-900/50 bg-red-950/20 p-4 text-center text-xs font-bold text-red-400">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="mt-8 flex flex-col gap-4">
        <form method="POST" action="{{ route('verification.verify-code') }}"
              x-data="{
                  digits: ['', '', '', '', '', ''],
                  code() { return this.digits.join(''); },
                  onInput(i, e) {
                      this.digits[i] = e.target.value.replace(/\D/g, '').slice(-1);
                      if (this.digits[i] && i < 5) this.$refs['d' + (i + 1)].focus();
                  },
                  onKeydown(i, e) {
                      if (e.key === 'Backspace' && !this.digits[i] && i > 0) this.$refs['d' + (i - 1)].focus();
                  },
                  onPaste(e) {
                      const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                      if (!text) return;
                      e.preventDefault();
                      text.split('').forEach((ch, i) => this.digits[i] = ch);
                      this.$refs['d' + Math.min(text.length, 6) - 1]?.focus();
                  },
              }">
            @csrf
            <input type="hidden" name="code" :value="code()">
            <div class="flex items-center justify-center gap-2 mb-6" @paste="onPaste">
                @for ($i = 0; $i < 6; $i++)
                    <input
                        type="text"
                        inputmode="numeric"
                        maxlength="1"
                        x-ref="d{{ $i }}"
                        x-model="digits[{{ $i }}]"
                        @input="onInput({{ $i }}, $event)"
                        @keydown="onKeydown({{ $i }}, $event)"
                        class="ui-input w-11 h-14 text-center text-xl font-black tracking-normal"
                        @if($i === 0) autofocus @endif
                    >
                @endfor
            </div>
            <button type="submit" class="ui-btn w-full py-4 text-[11px] uppercase tracking-[0.2em]">
                Verificar Código
            </button>
        </form>

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="w-full text-center text-[9px] font-black uppercase tracking-widest text-muted hover:text-gold transition underline underline-offset-4 py-2">
                Reenviar Código
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="text-center">
            @csrf
            <button type="submit" class="text-[9px] font-black uppercase tracking-widest text-muted hover:text-gold transition underline underline-offset-4">
                Cerrar Sesión
            </button>
        </form>
    </div>
</x-guest-layout>
