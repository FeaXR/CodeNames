<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Support\WordList;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GameController extends Controller
{
    public function home(): View
    {
        return view('home', [
            'languages' => WordList::languages(),
            'defaultLanguage' => config('codenames.default_language'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'language' => ['required', Rule::in(array_keys(WordList::languages()))],
        ]);

        $game = Game::start($validated['language']);
        $game->save();

        return redirect()->route('games.spymaster', [$game->code, $game->spymaster_token]);
    }

    public function join(Request $request): RedirectResponse
    {
        $code = strtoupper(trim((string) $request->input('code')));

        if (! Game::where('code', $code)->exists()) {
            return back()->withErrors(['code' => __('No game found with code :code.', ['code' => $code])])->withInput();
        }

        return redirect()->route('games.player', $code);
    }

    public function player(Game $game): View
    {
        return view('player', [
            'game' => $game,
            'state' => $game->stateFor(spymaster: false),
        ]);
    }

    public function playerState(Game $game): JsonResponse
    {
        return response()->json($game->stateFor(spymaster: false));
    }

    public function spymaster(Game $game, string $token): View
    {
        $this->authorizeSpymaster($game, $token);

        $playerUrl = route('games.player', $game->code);
        $spymasterUrl = route('games.spymaster', [$game->code, $token]);

        return view('spymaster', [
            'game' => $game,
            'state' => $game->stateFor(spymaster: true),
            'languages' => WordList::languages(),
            'playerUrl' => $playerUrl,
            'spymasterUrl' => $spymasterUrl,
            'playerQr' => $this->qr($playerUrl),
            'spymasterQr' => $this->qr($spymasterUrl),
        ]);
    }

    public function spymasterState(Game $game, string $token): JsonResponse
    {
        $this->authorizeSpymaster($game, $token);

        return response()->json($game->stateFor(spymaster: true));
    }

    public function reveal(Game $game, string $token, int $index): JsonResponse
    {
        return $this->mutate($game, $token, fn (Game $game) => $game->reveal($index));
    }

    public function endTurn(Game $game, string $token): JsonResponse
    {
        return $this->mutate($game, $token, fn (Game $game) => $game->endTurn());
    }

    public function finish(Game $game, string $token): JsonResponse
    {
        return $this->mutate($game, $token, fn (Game $game) => $game->finish());
    }

    public function newBoard(Request $request, Game $game, string $token): JsonResponse
    {
        $validated = $request->validate([
            'language' => ['required', Rule::in(array_keys(WordList::languages()))],
        ]);

        return $this->mutate($game, $token, fn (Game $game) => $game->deal($validated['language']));
    }

    /**
     * Apply a change under a row lock, so both spymaster devices can act at once safely.
     */
    protected function mutate(Game $game, string $token, Closure $change): JsonResponse
    {
        $this->authorizeSpymaster($game, $token);

        $game = DB::transaction(function () use ($game, $change) {
            $locked = Game::whereKey($game->getKey())->lockForUpdate()->firstOrFail();
            $change($locked);
            $locked->save();

            return $locked;
        });

        return response()->json($game->stateFor(spymaster: true));
    }

    protected function authorizeSpymaster(Game $game, string $token): void
    {
        abort_unless(hash_equals($game->spymaster_token, $token), 404);
    }

    protected function qr(string $url): string
    {
        return (new QRCode(new QROptions([
            'outputBase64' => false,
            'addQuietzone' => true,
            'quietzoneSize' => 2,
            'drawLightModules' => false,
            'svgAddXmlHeader' => false,
        ])))->render($url);
    }
}
