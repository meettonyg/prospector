# Podcast Prospector Vue 3 Frontend

A Vue 3 + Pinia search interface for podcast discovery, integrated with WordPress and the Guest Intel CRM.

## Quick Start

### Development Mode

```bash
# Navigate to Vue directory
cd assets/vue

# Install dependencies (first time only)
npm install

# Start development server with HMR
npm run dev
```

Then enable dev mode in WordPress:
```php
// wp-config.php
define('PROSPECTOR_DEV_MODE', true);
```

### Production Build

```bash
cd assets/vue
npm run build
```

Remove or set `PROSPECTOR_DEV_MODE` to `false` in wp-config.php.

---

## Architecture

```
assets/vue/
├── dist/                     # Built production files
├── src/
│   ├── App.vue               # Root component with mode toggle
│   ├── main.js               # Vue app entry point
│   │
│   ├── api/
│   │   └── prospectorApi.js  # Axios wrapper for REST endpoints
│   │
│   ├── components/
│   │   ├── common/           # Reusable UI components
│   │   ├── search/           # Traditional search interface
│   │   └── chat/             # Chat interface (feature-flagged)
│   │
│   ├── composables/          # Vue 3 composition functions
│   │   ├── useSearch.js
│   │   ├── useFilters.js
│   │   ├── useHydration.js
│   │   ├── useImport.js
│   │   └── useUserStats.js
│   │
│   ├── stores/               # Pinia state stores
│   │   ├── searchStore.js
│   │   ├── filterStore.js
│   │   ├── userStore.js
│   │   ├── chatStore.js
│   │   └── toastStore.js
│   │
│   ├── utils/
│   │   ├── constants.js      # Channels, modes, genres
│   │   ├── dataNormalizer.js # API response normalization
│   │   └── intentDetector.js # Chat intent detection
│   │
│   └── style.css             # Tailwind imports + custom
│
├── tailwind.config.js        # Scoped to #prospector-app
├── vite.config.js
└── package.json
```

---

## WordPress Integration

### PHP Files

| File | Purpose |
|------|---------|
| `includes/class-vue-assets.php` | Enqueues Vue bundle + config |
| `includes/class-rest-api.php` | REST endpoints for search, hydrate, import |
| `templates/vue-app.php` | Vue mount point in admin |

### REST Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/podcast-prospector/v1/search` | POST | Execute search |
| `/podcast-prospector/v1/hydrate` | POST | Check CRM status |
| `/podcast-prospector/v1/import` | POST | Import to pipeline |
| `/podcast-prospector/v1/user/stats` | GET | Get user stats |

### WordPress Config Object

The Vue app receives configuration via `window.PROSPECTOR_CONFIG`:

```javascript
{
  apiBase: '/wp-json/podcast-prospector/v1',
  nonce: 'wp_rest_nonce',
  userId: 123,
  guestIntelActive: true,
  membership: {
    level: 'pro',
    searchesRemaining: 45,
    searchCap: 50
  },
  features: {
    chat: false,        // Chat interface
    youtube: true,      // YouTube channel search
    summits: false,     // Virtual summits
    chatGpt: false      // AI-powered chat (Phase 7)
  },
  i18n: { ... }
}
```

---

## Components

### Common Components

| Component | Description |
|-----------|-------------|
| `ChannelDropdown.vue` | Podcasts/YouTube/Summits selector |
| `SearchInput.vue` | Main search text input with submit |
| `FilterPanel.vue` | Collapsible advanced filters |
| `ResultCard.vue` | Single podcast result with hydration badge |
| `ResultGrid.vue` | Grid layout for results |
| `Pagination.vue` | Page navigation |
| `SearchCapBadge.vue` | "X searches remaining" display |
| `ImportButton.vue` | Add to pipeline button |
| `EmptyState.vue` | Welcome screen / no results |
| `LoadingSpinner.vue` | Loading indicator |
| `ToastContainer.vue` | Toast notifications |

### Search Components

| Component | Description |
|-----------|-------------|
| `TraditionalSearch.vue` | Main search container |
| `SearchModeTabs.vue` | byPerson, byTitle, Advanced tabs |
| `ResultsToolbar.vue` | View toggle, bulk actions |
| `ResultsContainer.vue` | Results wrapper (grid/table) |
| `ResultTable.vue` | Table view for results |

### Chat Components (Feature Flagged)

| Component | Description |
|-----------|-------------|
| `ChatInterface.vue` | Main chat container |
| `ChatEmptyState.vue` | Welcome with example prompts |
| `ChatMessage.vue` | User/AI message bubbles |
| `ChatInput.vue` | Auto-resize textarea |
| `ChatResultCard.vue` | Inline result in chat |
| `QuickActionChips.vue` | Follow-up suggestions |

---

## Stores (Pinia)

### searchStore

```javascript
import { useSearchStore } from '@/stores/searchStore'

const store = useSearchStore()

// Actions
await store.search({ language: 'en' })
store.setChannel('youtube')
store.setMode('bytitle')
store.toggleSelection(index)
await store.refreshHydration()

// Getters
store.hydratedResults  // Results with CRM status merged
store.selectedCount    // Number of selected items
store.hasResults       // Boolean
```

### userStore

```javascript
import { useUserStore } from '@/stores/userStore'

const store = useUserStore()

// State
store.searchesRemaining
store.membershipLevel
store.guestIntelActive

// Getters
store.canSearch        // Has searches remaining
store.canImport        // Guest Intel is active
store.isPremium        // Pro or Enterprise
```

### filterStore

```javascript
import { useFilterStore } from '@/stores/filterStore'

const store = useFilterStore()

// Actions
store.setFilter('language', 'en')
store.clearFilters()

// Getters
store.filterParams     // Object for API call
store.hasActiveFilters
store.activeFilterCount
```

---

## Feature Flags

### Enabling Chat Interface

```php
// Via admin settings
update_option('prospector_enable_chat', true);

// Or in code
add_filter('prospector_features', function($features) {
    $features['chat'] = true;
    return $features;
});
```

### Enabling ChatGPT (Phase 7)

```php
// Requires OpenAI API key
update_option('prospector_openai_key', 'sk-...');
update_option('prospector_enable_chatgpt', true);
```

---

## Hydration System

The hydration system checks if search results already exist in Guest Intel:

1. **Search completes** → Results displayed
2. **Auto-hydration triggers** → Sends identifiers to `/hydrate` endpoint
3. **Backend checks** → Queries `pit_podcasts` and `pit_opportunities` tables
4. **Badges appear** → "In Pipeline" badges on tracked podcasts

### Identifier Priority

1. iTunes ID (most stable)
2. RSS URL
3. Podcast Index ID

---

## Styling

### Tailwind Scoping

All Tailwind styles are scoped to `#prospector-app` to prevent WordPress Admin conflicts:

```javascript
// tailwind.config.js
important: '#prospector-app',
corePlugins: { preflight: false }
```

### Design Tokens

```css
/* Primary - Sky Blue */
--color-primary-500: #0ea5e9;
--color-primary-600: #0284c7;

/* Neutrals - Slate */
--color-slate-500: #64748b;
--color-slate-800: #1e293b;
```

---

## Troubleshooting

### Vue app not loading

1. Check if `PROSPECTOR_DEV_MODE` matches your setup
2. Verify `dist/` folder exists with built files
3. Check browser console for errors

### Hydration badges not appearing

1. Verify Guest Intel plugin is active
2. Check `pit_podcasts` table exists
3. Test `/hydrate` endpoint directly

### WordPress Admin styles affected

1. Ensure `tailwind.config.js` has `important: '#prospector-app'`
2. Rebuild: `npm run build`
3. Clear WordPress cache

---

## Migration Status

| Phase | Description | Status |
|-------|-------------|--------|
| 1 | Foundation & Build Pipeline | ✅ Complete |
| 2 | Hydration Endpoint & Stores | ✅ Complete |
| 3 | Common Components | ✅ Complete |
| 4 | Traditional Search | ✅ Complete |
| 5 | Chat Interface | ✅ Complete |
| 6 | Import & Polish | ✅ Complete |
| 7 | ChatGPT Integration | ⏳ Optional |
| 8 | Testing & Docs | 🔲 In Progress |

---

## Version History

- **v3.0.0** - Vue 3 migration complete
- **v2.x** - Legacy vanilla JS version (deprecated)

