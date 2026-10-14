@extends('layouts.app')

@section('body-class', 'page-home')

@section('content')
    <main class="home">
        <h1 class="logo">Code<span>names</span></h1>

        <section class="panel">
            <h2>New game</h2>
            <p class="muted">Creates a board and opens the spymaster view. Share the links from there with the other spymaster and the players.</p>
            <form method="POST" action="{{ route('games.store') }}" class="stack">
                @csrf
                <label>
                    <span>Word list</span>
                    <select name="language">
                        @foreach ($languages as $code => $name)
                            <option value="{{ $code }}" @selected($code === $defaultLanguage)>{{ $name }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="btn btn-primary">Create game</button>
            </form>
        </section>

        <section class="panel">
            <h2>Join as player</h2>
            <form method="POST" action="{{ route('games.join') }}" class="stack">
                @csrf
                <label>
                    <span>Game code</span>
                    <input name="code" value="{{ old('code') }}" maxlength="8" autocomplete="off" autocapitalize="characters" placeholder="ABCDE" required>
                </label>
                @error('code')
                    <p class="error">{{ $message }}</p>
                @enderror
                <button type="submit" class="btn">Join</button>
            </form>
        </section>
    </main>
@endsection
