<?php

namespace App\Models;

use App\Support\WordList;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['code', 'spymaster_token', 'language', 'cards', 'starting_team', 'current_team', 'status', 'winner', 'version'])]
#[Hidden(['spymaster_token'])]
class Game extends Model
{
    public const RED = 'red';

    public const BLUE = 'blue';

    public const NEUTRAL = 'neutral';

    public const ASSASSIN = 'assassin';

    public const BOARD_SIZE = 25;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cards' => 'array',
            'version' => 'integer',
        ];
    }

    /**
     * Create a brand-new game with a fresh board.
     */
    public static function start(string $language): self
    {
        $game = new self([
            'code' => self::uniqueCode(),
            'spymaster_token' => Str::random(32),
        ]);

        $game->deal($language);

        return $game;
    }

    /**
     * Deal a new board onto this game, keeping its code and links.
     */
    public function deal(string $language): void
    {
        $starting = random_int(0, 1) ? self::RED : self::BLUE;
        $other = self::otherTeam($starting);

        $colors = array_merge(
            array_fill(0, 9, $starting),
            array_fill(0, 8, $other),
            array_fill(0, 7, self::NEUTRAL),
            [self::ASSASSIN],
        );
        shuffle($colors);

        $words = WordList::random($language, self::BOARD_SIZE);

        $this->fill([
            'language' => $language,
            'cards' => array_map(
                fn (string $word, string $color) => ['word' => $word, 'color' => $color, 'revealed' => false],
                $words,
                $colors,
            ),
            'starting_team' => $starting,
            'current_team' => $starting,
            'status' => 'playing',
            'winner' => null,
            'version' => ($this->version ?? 0) + 1,
        ]);
    }

    /**
     * Reveal a card and apply the game rules.
     */
    public function reveal(int $index): void
    {
        $cards = $this->cards;

        if ($this->isFinished() || ! isset($cards[$index]) || $cards[$index]['revealed']) {
            return;
        }

        $cards[$index]['revealed'] = true;
        $this->cards = $cards;
        $color = $cards[$index]['color'];

        if ($color === self::ASSASSIN) {
            $this->finish(self::otherTeam($this->current_team));
        } elseif (in_array($color, [self::RED, self::BLUE]) && $this->remaining($color) === 0) {
            $this->finish($color);
        } elseif ($color !== $this->current_team) {
            $this->current_team = self::otherTeam($this->current_team);
        }

        $this->touchVersion();
    }

    /**
     * Hand the turn over to the other team.
     */
    public function endTurn(): void
    {
        if ($this->isFinished()) {
            return;
        }

        $this->current_team = self::otherTeam($this->current_team);
        $this->touchVersion();
    }

    /**
     * End the game, optionally declaring a winner, and reveal the whole board.
     */
    public function finish(?string $winner = null): void
    {
        $this->status = 'finished';
        $this->winner = $winner;
        $this->touchVersion();
    }

    public function isFinished(): bool
    {
        return $this->status === 'finished';
    }

    /**
     * Number of unrevealed cards left for a team.
     */
    public function remaining(string $team): int
    {
        return collect($this->cards)
            ->where('color', $team)
            ->where('revealed', false)
            ->count();
    }

    /**
     * Board state as a given role is allowed to see it.
     *
     * Players only learn a card's color once it is revealed (or the game is over).
     *
     * @return array<string, mixed>
     */
    public function stateFor(bool $spymaster): array
    {
        $showAll = $spymaster || $this->isFinished();

        return [
            'code' => $this->code,
            'version' => $this->version,
            'language' => $this->language,
            'status' => $this->status,
            'winner' => $this->winner,
            'current_team' => $this->current_team,
            'starting_team' => $this->starting_team,
            'remaining' => [
                self::RED => $this->remaining(self::RED),
                self::BLUE => $this->remaining(self::BLUE),
            ],
            'cards' => array_map(fn (array $card) => [
                'word' => $card['word'],
                'revealed' => $card['revealed'],
                'color' => ($showAll || $card['revealed']) ? $card['color'] : null,
            ], $this->cards),
        ];
    }

    public static function otherTeam(string $team): string
    {
        return $team === self::RED ? self::BLUE : self::RED;
    }

    protected function touchVersion(): void
    {
        $this->version = $this->version + 1;
    }

    protected static function uniqueCode(): string
    {
        do {
            // Unambiguous characters only, so the code is easy to read out loud.
            $code = '';
            for ($i = 0; $i < 5; $i++) {
                $code .= 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'[random_int(0, 31)];
            }
        } while (self::where('code', $code)->exists());

        return $code;
    }
}
