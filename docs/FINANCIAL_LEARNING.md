# Financial learning, achievements and streaks

The authenticated Learning page combines a locally stored financial-literacy question bank, scored daily quiz attempts, achievement progress and learning streaks.

## Daily quiz and scoring

- The question bank is seeded by the feature migration and contains ten curated budgeting, saving, debt, spending, credit, planning, banking, investing and account-security questions.
- One question is selected deterministically for each local calendar date from the active question bank.
- The server selects the question and compares the submitted option with the stored correct option. The client never supplies a score, correct answer or user ID.
- Each user may submit one attempt per local calendar date. A database unique constraint and a per-user row lock make retries idempotent.
- A correct answer awards 10 points. Incorrect answers receive the stored explanation and do not earn quiz points.
- Attempts are retained with the question, selected option, result, points and answer timestamp.

## Achievement rules

Achievements are evaluated from persisted financial records, quiz attempts and streak state. The unique user/code key and transactional points update prevent duplicate awards.

| Achievement | Requirement | Bonus points |
|---|---|---:|
| First income recorded | At least one saved income | 15 |
| First expense tracked | At least one saved expense | 15 |
| First savings contribution | At least one saved contribution | 20 |
| Budget builder | At least one saved budget | 20 |
| Learning in motion | At least one completed quiz | 10 |
| Financial learner | 10 correct quiz answers | 30 |
| Money knowledge builder | 25 correct quiz answers | 60 |
| Three-day learning streak | Longest streak reaches 3 days | 20 |
| Week of consistency | Longest streak reaches 7 days | 50 |
| Thirty-day commitment | Longest streak reaches 30 days | 150 |

The Learning page evaluates eligibility using server-side queries, displays progress toward locked achievements and shows unlocked history. Repeated page views do not award points more than once.

## Streak and timezone policy

- A completed quiz attempt counts as that day's learning activity, whether the answer is correct or incorrect.
- The first attempt creates a streak using the configured application timezone.
- The selected timezone is stored on the user's streak record and remains fixed. Changing the application timezone later does not reinterpret earlier streak dates.
- A quiz on the next local calendar day increments the current streak. A missed local day resets the current streak to one on the next completed quiz. The longest streak is retained.
- A second submission on the same local date returns the original attempt and does not alter points or streaks.

## Privacy and integrity

Quiz attempts and achievement awards belong to the authenticated user. No leaderboard or public profile is created. Only question choices are accepted from the client; points, answer correctness, dates, streak transitions and eligibility are computed on the server.
