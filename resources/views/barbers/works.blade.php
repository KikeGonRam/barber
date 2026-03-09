<x-app-layout>
    <x-slot name="header">
        <h2 class="ui-title">Trabajos de <span class="text-gold">{{ $barber->name }}</span></h2>
        <p class="ui-subtitle">Galería de cortes y estilos realizados.</p>
    </x-slot>
    <div class="py-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach ($works as $work)
                <div class="ui-card p-4 flex flex-col items-center">
                    <div class="flex flex-wrap gap-2 mb-2">
                        @foreach ($work->images as $img)
                            <img src="{{ Storage::url($img->image) }}" class="h-32 w-32 rounded-xl object-cover" alt="Trabajo">
                        @endforeach
                    </div>
                    <h5 class="font-bold text-white">{{ $work->title }}</h5>
                    <p class="text-muted text-xs mb-2">{{ $work->description }}</p>
                    <!-- Reacciones -->
                    <form method="POST" action="{{ route('barbers.works.react', $work) }}" class="mb-2 flex gap-2">
                        @csrf
                        <button type="submit" name="type" value="like" class="ui-btn-secondary">👍</button>
                        <button type="submit" name="type" value="love" class="ui-btn-secondary">❤️</button>
                        <button type="submit" name="type" value="wow" class="ui-btn-secondary">😮</button>
                        <span class="text-xs text-muted">{{ $work->reactions()->count() }} reacciones</span>
                    </form>
                    <!-- Comentarios -->
                    <form method="POST" action="{{ route('barbers.works.comment', $work) }}" class="w-full mb-2">
                        @csrf
                        <textarea name="comment" class="ui-input w-full mb-2" rows="2" placeholder="Escribe un comentario..." required></textarea>
                        <div class="flex items-center gap-2">
                            <label class="text-xs text-muted">Calificación:</label>
                            <select name="rating" class="ui-input text-xs">
                                <option value="">Sin calificar</option>
                                @for ($i = 1; $i <= 5; $i++)
                                    <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
                            </select>
                            <button type="submit" class="ui-btn">Comentar</button>
                        </div>
                    </form>
                    <div class="w-full mt-2">
                        <h6 class="text-xs font-bold text-gold mb-1">Comentarios</h6>
                        @foreach ($work->comments as $comment)
                            <div class="bg-white/5 rounded-xl p-2 mb-1">
                                <span class="text-xs text-white font-bold">{{ $comment->user_id }}</span>
                                <span class="text-xs text-muted">{{ $comment->comment }}</span>
                                @if($comment->rating)
                                    <span class="text-xs text-gold">({{ $comment->rating }}/5)</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
