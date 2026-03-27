# View Books + Search/Filter Manual Test Checklist

## Setup
1. Run `npm start` in `Web-Systems-Project`.
2. Open `http://127.0.0.1:3000/books.html`.
3. Open browser DevTools console to confirm no runtime errors.

## Core Flow
1. Confirm page title and hero identify page as **View Books**.
2. Confirm navigation includes **View Books** and highlights it as active.
3. Confirm all book cards render on first load.
4. Confirm results counter displays total books on first load.

## Search Behavior
1. Enter partial title text (example: `dune`) and verify matching books remain.
2. Enter partial author text (example: `orwell`) and verify matching books remain.
3. Enter mixed-case search text (example: `AtOmIc`) and verify case-insensitive matching.
4. Enter leading/trailing whitespace (example: `   dune   `) and verify whitespace is ignored.

## Genre Filter Behavior
1. Click each genre chip and verify only books with matching `data-genre` remain.
2. Confirm **All** restores full genre set.
3. Confirm active chip styling updates correctly when switching genres.

## Combined Filtering
1. Select a genre and then type a query.
2. Verify result set is intersection logic: `matchesSearch && matchesGenre`.
3. Clear search while keeping a genre selected; verify genre filter still applies.

## Reset and Empty State
1. Apply filters that return no matches; verify **No books match your current filters.** appears.
2. Confirm no-results message hides immediately once any book matches again.
3. Click **Clear** button and verify:
   - search input is emptied,
   - genre resets to **All**,
   - all books are shown,
   - no-results is hidden,
   - focus returns to search input.

## Accessibility and Responsiveness
1. Keyboard-tab through search input, chips, and Clear button; confirm visible focus state.
2. Confirm search and genre controls are labeled for assistive tech.
3. Test at mobile width (`<=640px`) and tablet width (`<=960px`); verify controls and cards remain usable.

## Regression Checks
1. Click **Add** on any visible book and verify bag count increases.
2. Confirm cart toast still appears and includes selected book title.
3. Confirm no console errors after repeated filter interactions.
