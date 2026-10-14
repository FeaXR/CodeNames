<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Support\WordList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameTest extends TestCase
{
    use RefreshDatabase;

    protected function makeGame(string $language = 'en'): Game
    {
        $game = Game::start($language);
        $game->save();

        return $game;
    }

    protected function spymasterUrl(Game $game, string $path = ''): string
    {
        return "/g/{$game->code}/spymaster/{$game->spymaster_token}{$path}";
    }

    protected function indexOf(Game $game, string $color): int
    {
        return collect($game->cards)->search(fn ($card) => $card['color'] === $color && ! $card['revealed']);
    }

    public function test_every_language_has_enough_unique_words(): void
    {
        $this->assertArrayHasKey('en', WordList::languages());
        $this->assertArrayHasKey('hu', WordList::languages());

        foreach (array_keys(WordList::languages()) as $language) {
            $this->assertGreaterThanOrEqual(200, count(WordList::words($language)), $language);
        }
    }

    public function test_a_new_board_has_the_standard_distribution(): void
    {
        $game = $this->makeGame('hu');
        $colors = collect($game->cards)->countBy('color');

        $this->assertCount(25, $game->cards);
        $this->assertCount(25, collect($game->cards)->pluck('word')->unique());
        $this->assertSame(9, $colors[$game->starting_team]);
        $this->assertSame(8, $colors[Game::otherTeam($game->starting_team)]);
        $this->assertSame(7, $colors['neutral']);
        $this->assertSame(1, $colors['assassin']);
    }

    public function test_creating_a_game_redirects_to_the_spymaster_view(): void
    {
        $response = $this->post('/games', ['language' => 'en']);

        $game = Game::firstOrFail();
        $response->assertRedirect($this->spymasterUrl($game));
        $this->get($this->spymasterUrl($game))->assertOk()->assertSee($game->code);
    }

    public function test_players_only_see_colors_of_revealed_cards(): void
    {
        $game = $this->makeGame();
        $index = $this->indexOf($game, $game->starting_team);
        $this->postJson($this->spymasterUrl($game, "/reveal/{$index}"))->assertOk();

        $cards = $this->getJson("/g/{$game->code}/state")->assertOk()->json('cards');

        $this->assertSame($game->starting_team, $cards[$index]['color']);
        $this->assertCount(24, collect($cards)->whereNull('color'));
        $this->assertStringNotContainsString('assassin', $this->get("/g/{$game->code}")->getContent());
    }

    public function test_spymasters_see_every_color(): void
    {
        $game = $this->makeGame();

        $cards = $this->getJson($this->spymasterUrl($game, '/state'))->assertOk()->json('cards');

        $this->assertCount(0, collect($cards)->whereNull('color'));
    }

    public function test_a_wrong_token_cannot_see_or_change_the_board(): void
    {
        $game = $this->makeGame();

        $this->getJson("/g/{$game->code}/spymaster/nope/state")->assertNotFound();
        $this->postJson("/g/{$game->code}/spymaster/nope/reveal/0")->assertNotFound();
        $this->assertStringNotContainsString($game->spymaster_token, $this->get("/g/{$game->code}")->getContent());
    }

    public function test_revealing_another_teams_card_passes_the_turn(): void
    {
        $game = $this->makeGame();
        $team = $game->current_team;

        $this->postJson($this->spymasterUrl($game, '/reveal/'.$this->indexOf($game, $team)))
            ->assertJsonPath('current_team', $team);

        $this->postJson($this->spymasterUrl($game, '/reveal/'.$this->indexOf($game, 'neutral')))
            ->assertJsonPath('current_team', Game::otherTeam($team));
    }

    public function test_revealing_the_assassin_loses_the_game(): void
    {
        $game = $this->makeGame();
        $team = $game->current_team;

        $this->postJson($this->spymasterUrl($game, '/reveal/'.$this->indexOf($game, 'assassin')))
            ->assertJsonPath('status', 'finished')
            ->assertJsonPath('winner', Game::otherTeam($team));

        // Once finished, players see the whole key.
        $cards = $this->getJson("/g/{$game->code}/state")->json('cards');
        $this->assertCount(0, collect($cards)->whereNull('color'));
    }

    public function test_finding_all_agents_wins(): void
    {
        $game = $this->makeGame();
        $team = $game->current_team;

        foreach (collect($game->cards)->where('color', $team)->keys() as $index) {
            $response = $this->postJson($this->spymasterUrl($game, "/reveal/{$index}"));
        }

        $response->assertJsonPath('status', 'finished')
            ->assertJsonPath('winner', $team)
            ->assertJsonPath("remaining.{$team}", 0);
    }

    public function test_end_turn_finish_and_new_board(): void
    {
        $game = $this->makeGame('en');
        $team = $game->current_team;

        $this->postJson($this->spymasterUrl($game, '/end-turn'))->assertJsonPath('current_team', Game::otherTeam($team));
        $this->postJson($this->spymasterUrl($game, '/finish'))->assertJsonPath('status', 'finished');

        $response = $this->postJson($this->spymasterUrl($game, '/new-board'), ['language' => 'hu'])
            ->assertJsonPath('status', 'playing')
            ->assertJsonPath('language', 'hu')
            ->assertJsonPath('code', $game->code);

        $this->assertCount(0, collect($response->json('cards'))->where('revealed', true));
        $this->postJson($this->spymasterUrl($game, '/new-board'), ['language' => 'xx'])->assertUnprocessable();
    }

    public function test_joining_by_code(): void
    {
        $game = $this->makeGame();

        $this->post('/join', ['code' => strtolower($game->code)])->assertRedirect("/g/{$game->code}");
        $this->post('/join', ['code' => 'ZZZZZ'])->assertSessionHasErrors('code');
    }
}
