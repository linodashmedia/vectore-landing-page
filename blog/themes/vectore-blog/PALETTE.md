# The Vectore blog palette

Teal green leads. Everything else is chosen to sit under it rather than compete
with it. The values live in `style.css` on `:root` and are published to the
block editor through `theme.json`; this file is the reasoning behind them.

Every ratio below was measured in the pairing it is actually used in, and
`test/palette.test.php` re-measures them from the CSS on every run, so a retuned
token cannot quietly break a contrast promise.

## The teal ramp: one hue, three jobs

The trap this palette is built around: teal at its most recognisable (`#0D9488`)
manages only **3.74:1** on white. That is fine behind a heading or under a
border, and it is not enough for a link. So the hue is split by job.

| Token | Hex | Use it for | Measured |
|---|---|---|---|
| `--v-brand` | `#0D9488` | **Non-text only.** Fills, rules, dots, washes, and display type at 24px+ | 3.74:1 on white |
| `--v-accent` | `#0A7C6E` | **Every link, small label and button ground** | 5.10:1 on white |
| `--v-accent-deep` | `#076E5F` | Hover and pressed states | 6.17:1 on white |
| `--v-glow` | `#2ED3A7` | **Dark surfaces only** — the header bar, the footer, the newsletter card | 8.82:1 on ink |

The three are not interchangeable. `--v-brand` on white text fails; `--v-glow`
on white is 1.9:1 and vanishes; `--v-accent` on the ink card is 2.4:1 and goes
muddy. Each exists because the other two do not work where it is used.

## Neutrals

All tinted toward the teal, so nothing on the page reads as flat grey.

| Token | Hex | Use | Measured |
|---|---|---|---|
| `--v-ink` | `#07211F` | Headings, the header bar, the footer band | 16.85:1 on white |
| `--v-text` | `#2C3A38` | Body copy | 11.87:1 on white |
| `--v-muted` | `#5F7370` | Meta, captions, labels | 5.03:1 on white |
| `--v-hair` | `#DDE7E4` | Hairlines and card borders | — |
| `--v-surface` | `#F6FAF9` | The quietest raised surface | — |

## The canvas

| Token | Hex | Use |
|---|---|---|
| `--v-mint` | `#E4F5F0` | The wash behind the header and page head |
| `--v-mint-2` | `#D3EDE6` | The soft bubbles inside it |

Pale enough that body copy still clears 10:1 on top, deep enough that the fade
into white at the foot of the wash is visible.

## The warm complement, rationed

Coral and amber sit opposite teal on the wheel. They are for category chips, a
"new" flag, the occasional underline. They are not a second brand colour, and
the moment they start appearing in three places on one screen the balance is
gone.

| Token | Hex | Ink on it | White on it |
|---|---|---|---|
| `--v-coral` | `#F2714B` | **5.81:1** | 2.90:1 |
| `--v-amber` | `#F5A524` | **8.26:1** | 2.04:1 |

**Never put white text on either.** Both carry ink comfortably and neither
carries white at any size. The test asserts white *stays* unreadable on them, so
that if someone lightens a warm colour later, the rule is re-examined rather
than silently outgrown.

## Type

| Token | Family | Use |
|---|---|---|
| `--v-display` | Bricolage Grotesque 600/700 | Headings, the wordmark |
| `--v-body` | Figtree | Everything else |
| `--v-mono` | system monospace | The footer copyright, code |

Figtree is what the marketing site already uses, so the two properties read as
one brand. The display face is loaded at 600 and 700 only: asking for a weight
that is not loaded makes the browser synthesise bold by smearing the glyphs,
which is the single biggest thing working against a crisp halftone wordmark.

## Changing a colour

1. Edit the token in `style.css`. Nothing else hard-codes a colour.
2. Run `php test/palette.test.php`. It reads the new value and re-checks every
   pairing.
3. If a check fails, the fix is the colour, not the test. The ratios are the
   design, not a formality.
