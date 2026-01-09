# Public DOM Contract

## Required structures

### Hero
- .hero
- .hero-bar
- .hero-inner
- h1 inside .hero-inner

### Cards
- .grid
- .card
- .card-bar
- .card-body
- .top
- .meta
- .pill

## Rules
- No public page may introduce new card markup
- Cards must be rendered via /partials/card.php
- Hero must be rendered via /partials/hero.php
- CSS must target only canonical hooks
