<script setup>
import { onMounted } from 'vue';

onMounted(() => {
    const titleLink = document.querySelector('a.title');
    const span = titleLink?.querySelector('span');
    span?.remove();

    titleLink.style.display = 'flex';
    titleLink.style.justifyContent = 'center';
    titleLink.style.alignItems = 'center';

    titleLink.querySelectorAll('img')?.forEach(img => {
        img.style.width = '42px';
        img.style.height = '42px';
        img.style.marginRight = '8px';
    });
});
</script>

# Introduction

`Hunter` is a powerful utility for finding and processing Eloquent model records with a fluent, chainable API.
It provides a clean way to search for records based on specific criteria and execute multiple actions on them with
comprehensive error handling, logging, and advanced flow control.

# Installation

To install `Hunter`, you can use Composer. Run the following command in your terminal:

```bash
composer require e2tmk/hunter
```

# Usage

```php
use Hunter\Hunter;

Hunter::for(Campaign::class)
    ->find('scheduled_at', now())
    >modifyQueryUsing(fn($query) => $query->where('status', 'scheduled'))
    ->onBeforeThen(function (Campaign $campaign, Hunter $hunter) {
        // Validation with graceful failure
        if (!$campaign->hasContacts()) {
            $hunter->skip($campaign, 'No contacts to send to');
            return;
        }
    })
    ->then(function (Campaign $campaign) {
        ProcessCampaignJob::dispatch($campaign);
    })
    ->onAfterThen(function (Campaign $campaign) {
        $campaign->update(['status' => 'processing']);
    })
    ->hunt();

// Or using the helper function

hunter(Campaign::class)
    ->find(...)
    ->hunt();
```