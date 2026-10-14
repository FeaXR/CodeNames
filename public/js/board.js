/*
 * Renders a Codenames board and keeps it in sync with the server by polling.
 * Every device (both spymasters and any number of players) polls the same
 * game, so a reveal on one spymaster device shows up everywhere within a
 * poll interval.
 */
window.Codenames = (function () {
    const TEAM_NAMES = { red: 'Red', blue: 'Blue' };

    function mount(options) {
        const root = document.getElementById('game');
        const boardEl = root.querySelector('[data-board]');
        const statusEl = root.querySelector('[data-status]');
        const hintEl = root.querySelector('[data-hint]');
        const isSpymaster = options.role === 'spymaster';
        const csrf = document.querySelector('meta[name="csrf-token"]').content;

        let state = options.state;
        let armed = null; // index of the card waiting for a confirming second tap
        let busy = false;

        function render() {
            document.body.dataset.turn = state.current_team;
            document.body.dataset.status = state.status;

            renderStatus();
            renderBoard();

            if (hintEl) {
                hintEl.textContent = state.status === 'finished'
                    ? 'Game over. Start a new board below.'
                    : (armed !== null ? 'Tap the highlighted card again to reveal it.' : 'Tap a card twice to reveal it.');
            }

            const select = root.querySelector('[data-new-board] select');
            if (select && document.activeElement !== select) {
                select.value = state.language;
            }
        }

        function renderStatus() {
            const red = `<span class="score score-red">${state.remaining.red}</span>`;
            const blue = `<span class="score score-blue">${state.remaining.blue}</span>`;
            let message;

            if (state.status === 'finished') {
                message = state.winner
                    ? `<span class="team-${state.winner}">${TEAM_NAMES[state.winner]} team wins!</span>`
                    : 'Game finished';
            } else {
                message = `<span class="team-${state.current_team}">${TEAM_NAMES[state.current_team]}'s turn</span>`;
            }

            statusEl.innerHTML = `${red}<span class="status-message">${message}</span>${blue}`;
        }

        function renderBoard() {
            // Reuse existing card elements so transitions play smoothly.
            if (boardEl.children.length !== state.cards.length) {
                boardEl.innerHTML = '';
                state.cards.forEach((_, index) => {
                    const card = document.createElement(isSpymaster ? 'button' : 'div');
                    card.className = 'card';
                    card.dataset.index = index;
                    if (isSpymaster) {
                        card.type = 'button';
                        card.addEventListener('click', () => onCardClick(index));
                    }
                    card.innerHTML = '<span class="word"></span>';
                    boardEl.appendChild(card);
                });
            }

            state.cards.forEach((data, index) => {
                const card = boardEl.children[index];
                card.querySelector('.word').textContent = data.word;
                card.dataset.color = data.color || 'hidden';
                card.classList.toggle('revealed', data.revealed);
                card.classList.toggle('armed', armed === index);
                card.classList.toggle('long', data.word.length > 9);
                if (isSpymaster) {
                    card.disabled = data.revealed || state.status === 'finished';
                }
            });
        }

        function onCardClick(index) {
            if (busy || state.cards[index].revealed || state.status === 'finished') {
                return;
            }

            if (armed === index) {
                armed = null;
                post(`/reveal/${index}`);
            } else {
                armed = index;
                render();
            }
        }

        async function post(path, body) {
            busy = true;
            root.classList.add('busy');
            try {
                const response = await fetch(options.actionUrl + path, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify(body || {}),
                });

                if (response.status === 419) {
                    // Session expired; reloading picks up a fresh CSRF token.
                    window.location.reload();
                    return;
                }
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                update(await response.json(), true);
            } catch (error) {
                alert('Could not reach the server. Please try again.');
            } finally {
                busy = false;
                root.classList.remove('busy');
            }
        }

        function update(next, force) {
            if (!force && next.version === state.version) {
                return;
            }
            if (next.version !== state.version) {
                armed = null;
            }
            state = next;
            render();
        }

        async function poll() {
            try {
                const response = await fetch(options.stateUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
                if (response.ok) {
                    update(await response.json());
                }
            } catch (error) {
                // Temporary network hiccup; try again on the next tick.
            } finally {
                setTimeout(poll, document.hidden ? options.pollInterval * 4 : options.pollInterval);
            }
        }

        if (isSpymaster) {
            root.querySelector('[data-action="end-turn"]').addEventListener('click', () => {
                post('/end-turn');
            });

            root.querySelector('[data-action="finish"]').addEventListener('click', () => {
                if (confirm('Finish the game and show the full board to the players?')) {
                    post('/finish');
                }
            });

            root.querySelector('[data-new-board]').addEventListener('submit', (event) => {
                event.preventDefault();
                const language = event.target.language.value;
                if (confirm('Deal a new board? The current game will be replaced on every device.')) {
                    post('/new-board', { language });
                }
            });

            document.addEventListener('click', (event) => {
                if (armed !== null && !event.target.closest('.card')) {
                    armed = null;
                    render();
                }
            });
        }

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                fetch(options.stateUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
                    .then((response) => response.ok ? response.json() : null)
                    .then((next) => next && update(next))
                    .catch(() => {});
            }
        });

        render();
        setTimeout(poll, options.pollInterval);
    }

    return { mount };
})();
