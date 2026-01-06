# Drug Snapshot Architecture

## Overview

The Drug Snapshot system is a caching layer that stores drug information from the RxNorm API, automatically refreshing stale data to keep information current while minimizing external API calls.

## Key Constants

```php
// RxNormService.php
private const SNAPSHOT_STALE_DAYS = 10; // Days before snapshot is considered stale
```

## How It Works

### 1. Adding Medication (`POST /medications`)

```
User Request (rxcui: "213269")
        ↓
Check if user already has this medication
        ↓
Call: RxNormService::getOrCreateDrugSnapshot("213269")
        ↓
    ┌───────────────────────────────────┐
    │  Does snapshot exist in DB?       │
    └───────────────────────────────────┘
            ↓ Yes          ↓ No
            ↓              ↓
    ┌─────────────┐   ┌──────────────┐
    │ Is stale?   │   │ Fetch from   │
    │ (>10 days)  │   │ RxNorm API   │
    └─────────────┘   └──────────────┘
       ↓ Yes              ↓
       ↓                  ↓
    ┌──────────────────────────────┐
    │ Fetch fresh data from API    │
    │ Update snapshot record       │
    │ Set last_synced_at = now()   │
    └──────────────────────────────┘
            ↓
    Return DrugSnapshot
            ↓
    Create UserMedication record
            ↓
    Return success response
```

### 2. Fetching Medications (`GET /medications`)

```
User Request
        ↓
Load UserMedication with drugSnapshot relationship
        ↓
For each medication:
    ┌──────────────────────────────┐
    │ Is snapshot stale? (>10 days)│
    └──────────────────────────────┘
            ↓ Yes           ↓ No
            ↓               ↓
    ┌──────────────┐   Use existing
    │ Refresh from │   snapshot
    │ RxNorm API   │
    │ via service  │
    └──────────────┘
            ↓
    ┌──────────────────────────────┐
    │ If refresh fails, gracefully │
    │ use existing snapshot        │
    └──────────────────────────────┘
            ↓
    Map to response format
        ↓
Return medications array
```

## Database Schema

### `drug_snapshots` Table

```sql
CREATE TABLE drug_snapshots (
    rxcui VARCHAR PRIMARY KEY,
    drug_name VARCHAR NOT NULL,
    ingredient_base_names JSON,
    dosage_forms JSON,
    last_synced_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_last_synced (last_synced_at)
);
```

### `user_medications` Table

```sql
CREATE TABLE user_medications (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    rxcui VARCHAR NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (rxcui) REFERENCES drug_snapshots(rxcui) ON DELETE CASCADE,
    UNIQUE KEY unique_user_medication (user_id, rxcui),
    INDEX idx_rxcui (rxcui)
);
```

## Code Flow

### RxNormService::getOrCreateDrugSnapshot()

```php
public function getOrCreateDrugSnapshot(string $rxcui, bool $forceRefresh = false): ?DrugSnapshot
{
    // 1. Check if snapshot exists
    $snapshot = DrugSnapshot::find($rxcui);
    
    // 2. If snapshot exists and is fresh, return it
    if ($snapshot && !$forceRefresh && !$snapshot->isStale(self::SNAPSHOT_STALE_DAYS)) {
        return $snapshot;
    }
    
    // 3. Fetch fresh data from API
    $drugData = $this->fetchDrugFromApi($rxcui);
    
    if (!$drugData) {
        return null;
    }
    
    // 4. Create or update snapshot
    return DrugSnapshot::updateOrCreate(
        ['rxcui' => $rxcui],
        [
            'drug_name' => $drugData['name'],
            'ingredient_base_names' => $drugData['ingredient_base_names'],
            'dosage_forms' => $drugData['dosage_forms'],
            'last_synced_at' => now(),
        ]
    );
}
```

### UserMedicationController::index()

```php
public function index(Request $request): JsonResponse
{
    $medications = UserMedication::with('drugSnapshot')
        ->where('user_id', $request->user()->id)
        ->orderBy('created_at', 'desc')
        ->get();

    return response()->json([
        'message' => 'Medications retrieved successfully',
        'count' => $medications->count(),
        'data' => $medications->map(function ($medication) {
            $snapshot = $medication->drugSnapshot;
            
            // Check if snapshot is stale and refresh if needed
            if ($snapshot->isStale()) {
                $snapshot = $this->rxNormService->getOrCreateDrugSnapshot($snapshot->rxcui);
                
                // If refresh failed, use existing snapshot
                if (!$snapshot) {
                    $snapshot = $medication->drugSnapshot;
                }
            }
            
            return [
                'id' => $medication->id,
                'rxcui' => $medication->rxcui,
                'drug_name' => $snapshot->drug_name,
                'ingredient_base_names' => $snapshot->ingredient_base_names ?? [],
                'dosage_forms' => $snapshot->dosage_forms ?? [],
                'added_at' => $medication->created_at->toISOString(),
            ];
        }),
    ]);
}
```

## Performance Benefits

### Scenario: 100 Users, Each with 5 Medications (500 Total Medication Records)

**Without Snapshots:**
- Every medication fetch = 500 API calls
- Every user viewing their list = 5 API calls per user
- Daily API calls (100 users check 3x/day) = 1,500 calls

**With Snapshots (10-day TTL):**
- Unique drugs: ~50 different medications
- Initial population: 50 API calls
- Refresh cycle: 50 calls every 10 days = 5 calls/day
- Daily API calls: **~5 calls** (97% reduction)

### Real-World Example

```
Day 1:
- User A adds Aspirin (rxcui: 213269) → API call, snapshot created
- User B adds Aspirin (rxcui: 213269) → Uses cached snapshot, no API call ✓
- User C views medications including Aspirin → Uses cached snapshot, no API call ✓

Day 11:
- User A views medications → Aspirin snapshot is stale → API refresh
- User B views medications → Uses refreshed snapshot, no API call ✓

Day 15:
- User D adds Aspirin → Uses fresh snapshot, no API call ✓
```

## Error Handling

### Graceful Degradation

If the RxNorm API is unavailable during a refresh attempt:

```php
// In index() method
if ($snapshot->isStale()) {
    $refreshedSnapshot = $this->rxNormService->getOrCreateDrugSnapshot($snapshot->rxcui);
    
    // If refresh failed, use existing snapshot (graceful degradation)
    if (!$refreshedSnapshot) {
        $snapshot = $medication->drugSnapshot; // Use stale data
    } else {
        $snapshot = $refreshedSnapshot;
    }
}
```

**Result**: Users still get their medication list with slightly outdated information rather than an error.

## Testing Strategy

### Unit Tests
- ✅ `isStale()` method correctly identifies stale snapshots
- ✅ `getOrCreateDrugSnapshot()` creates new snapshots
- ✅ `getOrCreateDrugSnapshot()` reuses fresh snapshots
- ✅ `getOrCreateDrugSnapshot()` refreshes stale snapshots

### Feature Tests
- ✅ Adding medication creates snapshot (first time)
- ✅ Adding medication reuses fresh snapshot (no API call)
- ✅ Adding medication refreshes stale snapshot
- ✅ Fetching medications refreshes stale snapshots
- ✅ Fetching medications doesn't refresh fresh snapshots
- ✅ Graceful degradation when API fails during fetch

## Future Enhancements

### Optional: Batch Refresh via Cron Job

While not currently implemented, you could add a scheduled command:

```php
// app/Console/Commands/RefreshStaleSnapshots.php
php artisan snapshots:refresh-stale

// This would:
// 1. Find all snapshots older than 10 days
// 2. Refresh them in batches
// 3. Run daily during low-traffic hours
```

**Benefits:**
- Proactive updates (users never see stale data)
- Controlled API usage
- Better monitoring of API health

**Current Approach (On-Demand) is Preferred Because:**
- Simpler implementation
- No cron job dependencies
- Only refreshes actually-used medications
- Natural load distribution

## Monitoring & Metrics

### Recommended Metrics to Track

```php
// Log snapshot operations
Log::info('Snapshot reused', ['rxcui' => $rxcui, 'age_days' => $age]);
Log::info('Snapshot refreshed', ['rxcui' => $rxcui, 'old_age_days' => $age]);
Log::warning('Snapshot refresh failed', ['rxcui' => $rxcui, 'error' => $e->getMessage()]);
```

### Key Performance Indicators (KPIs)

1. **Cache Hit Rate**: % of requests served from fresh cache
2. **API Call Reduction**: Total API calls vs. without caching
3. **Refresh Success Rate**: % of successful snapshot refreshes
4. **Average Snapshot Age**: Mean age of snapshots being used

## Configuration

### Adjusting Staleness Threshold

```php
// In RxNormService.php
private const SNAPSHOT_STALE_DAYS = 10; // Change this value

// Options:
// 7 days  = More frequent updates, higher API usage
// 10 days = Balanced (recommended)
// 30 days = Less frequent updates, lower API usage
```

Consider adjusting based on:
- RxNorm API update frequency
- Your API rate limits
- Data freshness requirements
- User expectations
