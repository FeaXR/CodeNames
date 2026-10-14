@extends('layouts.app')

@section('title', "Spymaster · {$game->code}")
@section('body-class', 'page-board role-spymaster')

@section('content')
    <div class="game" id="game">
        <header class="bar">
            <a href="{{ route('home') }}" class="logo logo-small">Code<span>names</span></a>
            <div class="status" data-status></div>
            <div class="code">Spymaster · <strong>{{ $game->code }}</strong></div>
        </header>

        <div class="board" data-board></div>

        <p class="hint" data-hint>Tap a card twice to reveal it.</p>

        <section class="controls">
            <button type="button" class="btn" data-action="end-turn">End turn</button>
            <button type="button" class="btn btn-danger" data-action="finish">Finish game</button>

            <form class="new-board" data-new-board>
                <select name="language" aria-label="Word list">
                    @foreach ($languages as $code => $name)
                        <option value="{{ $code }}" @selected($code === $game->language)>{{ $name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary">New board</button>
            </form>
        </section>

        <details class="share">
            <summary>Share links &amp; QR codes</summary>
            <div class="share-grid">
                <div class="share-item">
                    <h3>Players</h3>
                    <div class="qr">{!! $playerQr !!}</div>
                    <input readonly value="{{ $playerUrl }}" onclick="this.select()">
                    <p class="muted">Open on any number of devices. Shows colors only for revealed cards.</p>
                </div>
                <div class="share-item">
                    <h3>Other spymaster</h3>
                    <div class="qr">{!! $spymasterQr !!}</div>
                    <input readonly value="{{ $spymasterUrl }}" onclick="this.select()">
                    <p class="muted">Keep this one secret — it shows the full key.</p>
                </div>
            </div>
        </details>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/board.js') }}?v={{ filemtime(public_path('js/board.js')) }}"></script>
    <script>
        Codenames.mount({
            role: 'spymaster',
            state: @json($state),
            stateUrl: @json($spymasterUrl.'/state'),
            actionUrl: @json($spymasterUrl),
            pollInterval: @json(config('codenames.poll_interval')),
        });
    </script>
@endpush
