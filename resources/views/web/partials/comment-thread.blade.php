@php
    $depth = $depth ?? 0;
@endphp
<article class="comment-item comment-item--depth-{{ min($depth, 6) }}" id="c-{{ $comment->id }}">
    <div class="comment-top">
        <strong>{{ $comment->user->name ?? 'Usuario' }}</strong>
        <span class="comment-points">{{ $comment->points }} pts</span>
    </div>
    <p>{{ $comment->body }}</p>
    @auth
        <div class="comment-actions">
            <form method="post" action="{{ route('posts.comments.vote', $comment) }}" style="display:inline;">
                @csrf
                <input type="hidden" name="value" value="1">
                <button type="submit" class="btn-secondary">+1</button>
            </form>
            <form method="post" action="{{ route('posts.comments.vote', $comment) }}" style="display:inline;">
                @csrf
                <input type="hidden" name="value" value="-1">
                <button type="submit" class="btn-secondary">−1</button>
            </form>
            <button
                type="button"
                class="btn-secondary blade-comment-reply"
                data-parent-id="{{ $comment->id }}"
                data-author-name="{{ $comment->user->name ?? 'Usuario' }}"
            >Responder</button>
        </div>
    @endauth
    @if($comment->relationLoaded('replies') && $comment->replies->count())
        <div class="comment-replies">
            @foreach($comment->replies as $child)
                @include('web.partials.comment-thread', ['comment' => $child, 'video' => $video, 'depth' => $depth + 1])
            @endforeach
        </div>
    @endif
</article>
