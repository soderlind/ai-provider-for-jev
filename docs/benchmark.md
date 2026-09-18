# Benchmark

Performance of the [Jev Comment Triage](examples/jev-comment-triage/jev-comment-triage.php)
example plugin, which calls the Jev API synchronously inside WordPress's comment
pipeline. The numbers below measure the real added latency a commenter
experiences.

## Method

- **Site:** WordPress multisite subsite on Local (`plugins.local`).
- **Path:** each comment is inserted with `wp_new_comment()`, which fires the
  plugin's `pre_comment_approved` triage → **one live Jev API call** batching two
  questions (a spam Noul + a toxicity Score).
- **Sample:** 100 measured comments (plus one warm-up excluded from stats) drawn
  from a mixed corpus (benign, questions, spam, toxic).
- **Execution:** single process, sequential, via `wp eval-file`. The
  comment-flood throttle was disabled for the run.
- **Model:** `jev-latest`.

Timing wraps each `wp_new_comment()` call, so it includes WordPress overhead plus
the Jev round trip; the API call dominates.

## Results

| Metric                   | Value            |
| ------------------------ | ---------------- |
| Comments measured        | 100 (0 errors)   |
| Wall time                | 129.9 s          |
| Throughput (sequential)  | 0.77 comments/s  |

### Per-comment latency

| Percentile | Latency (ms) |
| ---------- | ------------ |
| min        | 612          |
| mean       | 1299         |
| p50        | 1392         |
| p90        | 1562         |
| p95        | 1680         |
| p99        | 1797         |
| max        | 1985         |

### Outcome distribution

| Result        | Count |
| ------------- | ----- |
| `spam`        | 20    |
| held (`0`)    | 80    |
| approved (`1`)| 0     |

> The 20 `spam` results were forced by the plugin (`is_spam > 0.85`). `approved`
> is 0 only because the site's **Comment author must have a previously approved
> comment** setting held every first-time commenter by default — the triage
> passes those through, so WordPress's own decision (hold) applies. With that
> setting off, benign/question comments approve, toxic comments hold, and spam is
> flagged.

## Analysis

- The plugin adds **~1.3 s mean (p95 ~1.7 s, p99 ~1.8 s)** of **synchronous**
  latency to every comment submission. A bare `wp_new_comment()` is single-digit
  milliseconds, so the Jev round trip dominates end to end.
- Latency is tight and predictable (0.6–2.0 s across 100 calls) with **no
  timeouts or errors**, comfortably inside Jev's rate limits (1,200 requests per
  minute).
- Batching both questions into one request keeps it to a single API call per
  comment; asking them separately would roughly double the latency and cost.

## Production recommendations

The example runs inline for clarity. For real traffic:

1. **Move triage off the request path.** Insert the comment first, then evaluate
   asynchronously (`wp_schedule_single_event()` or Action Scheduler) and update
   the status with `wp_set_comment_status()`. Comment submission stays fast; the
   moderation decision lands a moment later.
2. **Batch across comments.** Evaluate several pending comments per request to
   push throughput well above the ~0.77/s of one-at-a-time synchronous calls.
3. **Cache / short-circuit.** Skip the API for trusted authors (previously
   approved, logged-in editors) to avoid paying latency where it isn't needed.

## Reproducing

Insert varied comments through `wp_new_comment()` (not `wp comment create`, which
uses `wp_insert_comment()` and bypasses the moderation hooks), timing each call
and recording `get_comment()->comment_approved`. Disable the flood throttle for
the batch:

```php
remove_filter( 'wp_is_comment_flood', 'wp_check_comment_flood', 10 );
add_filter( 'wp_is_comment_flood', '__return_false', 999 );
```
