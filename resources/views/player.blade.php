@extends('layouts.app')

@section('title', "Codenames · {$game->code}")
@section('body-class', 'page-board role-player')

@section('content')
    <div class="game" id="game">
        <header class="bar">
            <a href="{{ route('home') }}" class="logo logo-small">Code<span>names</span></a>
            <div class="status" data-status></div>
            <div class="code">Game <strong>{{ $game->code }}</strong></div>
        </header>

        <div class="board" data-board></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/board.js') }}?v={{ filemtime(public_path('js/board.js')) }}"></script>
    <script>
        Codenames.mount({
            role: 'player',
            state: @json($state),
            stateUrl: @json(route('games.player.state', $game->code)),
            pollInterval: @json(config('codenames.poll_interval')),
        });
    </script>
@endpush
