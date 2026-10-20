# Admin panel guide

The back office is a Filament panel on its own subdomain (`ADMIN_DOMAIN`, e.g. `admin.example.co.ke`). Staff accounts (`admins` table) are separate from player accounts: a player login can't open the panel, and an admin login can't place bets.

Times in the panel are EAT. Money is KES.

- [Signing in](#signing-in)
- [Roles at a glance](#roles-at-a-glance)
- [What every role sees](#what-every-role-sees)
- [Super admin](#super-admin)
- [Manager](#manager)
- [Trader](#trader)
- [Finance](#finance)
- [Support](#support)
- [Task reference](#task-reference)
- [Audit trail](#audit-trail)

---

## Signing in

1. Open the admin domain and sign in with your email and password.
2. **The first time**, you must set up an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password, …). Scan the QR code, enter the 6-digit code, and save the recovery codes somewhere safe. You can't use the panel until this is done.
3. After that, every sign-in asks for the current code from the app, or one recovery code.

**Lost your phone?** Use a recovery code. If you have none left, ask a super admin to **Reset 2FA** on your account; you'll set the app up again at your next sign-in.

Your profile (avatar menu → **Profile**) is where you change your name, email and password, and manage your authenticator and recovery codes.

---

## Roles at a glance

Each admin has exactly one role. The super admin can do everything; the others get these permissions:

| Permission | Manager | Trader | Finance | Support |
|---|:-:|:-:|:-:|:-:|
| View activity log | ✓ | | | |
| Manage competitions | ✓ | | | |
| Manage odds (suspend, reopen, override prices) | ✓ | ✓ | | |
| View players | ✓ | | ✓ | ✓ |
| Manage players (suspend, reinstate) | ✓ | | | |
| Adjust balances | | | ✓ | |
| Approve withdrawals | ✓ | | ✓ | |
| Reconcile payments | ✓ | | ✓ | |
| View bets | ✓ | ✓ | ✓ | ✓ |
| Settle bets (results, voids, cancellations) | ✓ | ✓ | | |
| Manage jackpots | ✓ | ✓ | | |
| Give free bets | ✓ | | ✓ | |
| Manage promotions | ✓ | | | |
| View reports | ✓ | | ✓ | |
| Export data | ✓ | | ✓ | |
| Manage admins | super admin only | | | |

Permissions are created by `Database\Seeders\AdminRoleSeeder`. Run it again after any deploy that changes roles (see the deploy guide).

### Menu by role

| Menu item | Super admin | Manager | Trader | Finance | Support |
|---|:-:|:-:|:-:|:-:|:-:|
| Dashboard | ✓ | ✓ | ✓ | ✓ | ✓ |
| Players | ✓ | ✓ | | ✓ | ✓ |
| Betting → Bets | ✓ | ✓ | ✓ | ✓ | ✓ |
| Betting → Jackpots | ✓ | ✓ | ✓ | ✓ | ✓ |
| Betting → Risk | ✓ | ✓ | ✓ | ✓ | ✓ |
| Trading → Competitions, Fixtures | ✓ | ✓ | ✓ | ✓ | ✓ |
| Payments → Deposits, Withdrawals | ✓ | ✓ | | ✓ | ✓ |
| Payments → Reconciliation | ✓ | ✓ | | ✓ | |
| Reports → Reports | ✓ | ✓ | | ✓ | |
| Marketing → Promotions | ✓ | ✓ | | | |
| Access → Activity Log | ✓ | ✓ | | | |
| Access → Admins | ✓ | | | | |

Seeing a page doesn't mean you can act on it. For example, Support can open Deposits but has no buttons there, and everyone can open a fixture but only roles that settle bets see **Enter result**. Buttons you aren't allowed to use are hidden.

---

## What every role sees

### Dashboard

| Section | Who sees it | What it shows |
|---|---|---|
| Period switch (**Today**, **Last 7 days**, **Last 30 days**) | Report viewers | Sets the period for Key figures. |
| Key figures (EAT) | Report viewers | Nine cards: Turnover, GGR, NGR, Promotion costs, Sportsbook hold, Active players, Deposits, Withdrawals, New sign-ups. Each card has the change against the previous period of the same length ("+12.5% vs the same time yesterday"), a 30-day trend line, and a colour. |
| Turnover chart | Report viewers | Turnover and GGR for each of the last 30 days. |
| Odds API credits | Odds and competition managers | Credits left on the plan, this month's spend against the cap, and whether the feed is `live` or `replay`. |
| Recent Odds API calls | Odds and competition managers | The latest feed calls and what each cost. |

How to read the cards:

- **Green** means the figure went up, which is good for the business. **Red** means it went down.
- **Promotion costs, Deposits and Withdrawals** are always gold (neutral). A rise there is neither good nor bad on its own.
- **Grey** means no change.
- **Today** is compared with yesterday up to the same time of day, so the morning figures aren't measured against a full day.

Definitions:

- **Turnover**: cash staked on bets and jackpot entries. Free-bet stakes are not cash, so they don't count.
- **GGR** (gross gaming revenue): cash stakes, minus stakes returned (voids, cancellations) and winnings paid, before tax. It includes the jackpot.
- **NGR** (net gaming revenue): GGR minus promotion costs (multibet bonuses and free-bet winnings).
- **Sportsbook hold**: sportsbook GGR as a percentage of sportsbook turnover.
- **Active players**: players who placed at least one bet or jackpot entry in the period.

The figures are worked out from the ledger each time the page loads. Reload the page to refresh them.

Money counts on the day it moved in the ledger. A result corrected on Tuesday for a Saturday match changes Tuesday's figures, not Saturday's. Taxes are reported separately (Reports) and never enter GGR or NGR.

### Notifications (bell icon)

Finished exports appear here with a download link.

---

## Super admin

Everything in every section below, plus:

### Manage admins (Access → Admins)

- **New admin**: name, email, role, password. They set up their authenticator at first sign-in.
- **Edit**: change name, email, role or **Active**. Leave the password blank to keep it. Untick **Active** to lock someone out without deleting their history. Admins can't be deleted.
- **Reset 2FA**: clears an admin's authenticator so they set it up again at next sign-in.
- The list shows each admin's role, whether 2FA is set up, and their last sign-in.

The first super admin is created on the server with `php artisan admin:create` (see the deploy guide).

### Things only a super admin should do

- Change roles. The permission set for each role lives in code (`AdminRoleSeeder`); changing what a role can do is a code change, not a panel setting.
- Review the activity log for other admins' changes.

---

## Manager

The operator's day-to-day lead. Can do everything except adjust balances and manage admins.

Typical day:

1. **Dashboard**: check yesterday's and the last 7 days' figures.
2. **Payments → Reconciliation**: clear anything under **Needs attention**.
3. **Payments → Withdrawals**: filter **Needs attention** and approve or reject large withdrawals.
4. **Betting → Risk**: look at the matches with the largest worst case.
5. **Trading → Fixtures**: settle UEFA Champions League matches by hand (they aren't settled automatically).
6. **Marketing → Promotions**: keep the promotions page current.

Tasks: [Competitions](#turn-a-competition-on-or-off), [Odds](#suspend-reopen-or-reprice-a-market), [Results](#enter-or-correct-a-result), [Bets](#cancel-a-bet-or-void-a-leg), [Jackpots](#run-a-jackpot), [Players](#look-up-a-player), [Suspend](#suspend-or-reinstate-a-player), [Free bets](#give-or-void-a-free-bet), [Withdrawals](#approve-or-reject-a-withdrawal), [Deposits](#resolve-a-deposit), [Reconciliation](#daily-reconciliation), [Reports](#reports-and-exports), [Promotions](#publish-a-promotion), [Activity log](#audit-trail).

---

## Trader

Owns prices, results and jackpots. Can't see players or payments.

Typical day:

1. **Dashboard**: check the Odds API credits card. The free plan has 500 credits a month, and the sync stops buying odds when it reaches the cap minus the reserve.
2. **Betting → Risk**: watch exposure. Suspend a market if one pick's worst case is too large.
3. **Trading → Fixtures**: settle UEFA Champions League matches by hand once they finish. Fix any wrong result.
4. **Betting → Jackpots**: build next week's jackpot and publish it.

Tasks: [Odds](#suspend-reopen-or-reprice-a-market), [Results](#enter-or-correct-a-result), [Void a match](#void-a-match), [Bets](#cancel-a-bet-or-void-a-leg), [Jackpots](#run-a-jackpot).

---

## Finance

Owns money in and out, reconciliation, reports and exports. Can't change prices or settle bets.

Typical day:

1. **Payments → Reconciliation**:
   - Clear **Needs attention**.
   - Compare **M-Pesa against the ledger** for yesterday.
   - Tap **Check B2C balance** to make sure there's float to pay withdrawals.
2. **Payments → Withdrawals**: approve or reject withdrawals awaiting approval. Resolve stuck payouts.
3. **Payments → Deposits**: credit, assign or dismiss deposits that need a person.
4. **Reports**: run the period's figures and download the tax report.

Tasks: [Players](#look-up-a-player), [Adjust balance](#adjust-a-balance), [Free bets](#give-or-void-a-free-bet), [Withdrawals](#approve-or-reject-a-withdrawal), [Stuck payouts](#stuck-payouts), [Deposits](#resolve-a-deposit), [Reconciliation](#daily-reconciliation), [Reports](#reports-and-exports).

---

## Support

Answers player questions. Read-only: can look up players, their bets, deposits and withdrawals, and the risk and fixtures pages, but has no buttons that change anything.

Common questions:

| Player asks | Where to look |
|---|---|
| "My deposit hasn't arrived" | Players → the player → **Deposits** tab, or Payments → Deposits filtered by their phone. Status **Pending** means the system is still asking M-Pesa. **Mismatch** or **Unmatched** means Finance has to act, so pass it on. |
| "Where's my withdrawal?" | The player → **Withdrawals** tab. **Awaiting approval** waits for Finance. **Processing** means it was sent to M-Pesa. **Failed** or **Rejected** means the money is back in the wallet. |
| "Why did my bet lose / was voided?" | Betting → Bets → the bet. Each leg shows its result or void reason. |
| "I can't bet or deposit" | The player's status (suspended?) and whether they're on a break. A break can't be ended early, by anyone. |
| "I want to close my account" | They can delete it themselves under Account settings → Profile. To stop gambling instead, point them to Take a break. |

Escalate anything needing a change to a Manager or Finance.

---

## Task reference

### Turn a competition on or off

*Manage competitions.* Trading → Competitions. Toggle **Synced**.

- Turning a competition on adds it to the player site after the next hourly fixture sync.
- It also adds it to the daily odds purchase, and each synced competition costs Odds API credits on every sync.
- **Order** sets its position in the sidebar.

### Suspend, reopen or reprice a market

*Manage odds.* Trading → Fixtures → open the match → **Markets** table.

- **Suspend**, with a reason, stops betting on that market at once.
- **Reopen** lets betting resume. The system still suspends markets on its own at kick-off, when fewer than 3 bookmakers price the match, or when odds are over 26 hours old.
- **Override prices**: enter a new price for any outcome. Leave a box blank to keep the feed price shown under it. A reason is required. Overridden prices stay through later odds syncs until you open **Override prices** again and empty the box.
- **History**: every price change and suspension on the market.

### Enter or correct a result

*Settle bets.* Trading → Fixtures → the match (after kick-off) → **Enter result**.

- Enter the full-time score. The half-time score is only needed for Half-time/Full-time bets.
- Bets settle as soon as you save. Jackpot matches settle too.
- **Correct result** reopens bets already settled on the match. Anything they paid is taken back, and they settle again on the new score. A note saying why is required.
- Most competitions settle on their own from the scores feed about 2¼ hours after kick-off. UEFA Champions League matches are settled by hand (`sportsbook.settlement.manual_competitions`).

### Void a match

*Settle bets.* The match → **Void match**, with a reason. Use it for a match that was abandoned or never played. Every leg on it counts as 1.00, singles are refunded, and betting on it stops. Matches moved by more than 48 hours are voided automatically.

### Cancel a bet or void a leg

*Settle bets.* Betting → Bets → the bet.

- **Cancel bet** (open bets only), with a reason: the stake is refunded and the bet will never pay.
- **Void leg** (Legs table), with a reason: for a pick priced in error. The leg counts as 1.00 and the bet settles again. If the bet had already settled, the difference is taken back or paid. The leg stays void even if the result is corrected later.

### Run a jackpot

*Manage jackpots* to create or change one. Anyone who can view bets can see jackpots.

1. Betting → Jackpots → **New jackpot**:
   - name;
   - entry price (default KES 99);
   - **Void matches allowed** (default 3);
   - **Matches**: 13 to 17 upcoming fixtures;
   - prize tiers (**Matches right** → **Prize**; default 17 → KES 1M, 16 → KES 100K, 15 → KES 20K).
2. The jackpot starts as a **Draft**. You can edit it only while it is a draft.
3. **Publish** it. Players can enter until the first match kicks off.
4. It settles on its own once every match has a result or is void. **Settle now** appears only when all matches are decided, as a manual fallback.
5. **Cancel jackpot**, with a reason, refunds every entry. It can't be undone.

If more matches are void than allowed, the jackpot cancels itself and refunds everyone. A result corrected after settlement reopens the jackpot and settles it again.

### Look up a player

*View players.* Players. Search by phone number. Filter by status, or by whether they're on a break. The player page shows their details, balance and status, with tabs for **Bets**, **Deposits**, **Withdrawals**, **Free bets** and **Transactions** (the full ledger).

### Suspend or reinstate a player

*Manage players.* The player → **Suspend**, with a reason. A suspended player can log in and see their wallet but can't deposit, withdraw or bet. Use **Reinstate** to undo it.

A suspension is a staff decision. A break (cool-off or self-exclusion) is the player's own, and staff can't end it.

### Adjust a balance

*Adjust balances* (Finance and super admin only). The player → **Adjust balance**: amount (use a minus sign to take money away) and a reason. It is recorded in the ledger with your name. Use it for goodwill credits and corrections. Never use it to "fix" a deposit: resolve the deposit instead, so M-Pesa and the ledger still agree.

### Give or void a free bet

*Give free bets.* The player → **Give free bet**:

- amount;
- **Minimum total odds** (default 1.50);
- **Days to use it** (default 7);
- **Why**.

The player uses it whole as the stake on one bet; a win pays the winnings above the stake, as cash. To take one back before it is used: the player → **Free bets** tab → **Void**.

### Approve or reject a withdrawal

*Approve withdrawals.* Payments → Withdrawals. Filter **Needs attention**, or status **Awaiting approval**. Withdrawals of KES 20,000 or more wait here; smaller ones are paid automatically.

- **Approve** sends it to M-Pesa B2C.
- **Reject**, with a reason, returns the money to the player's wallet.
- **Check status** (status **Processing**) asks M-Pesa what happened.

### Stuck payouts

If a payout has been **Processing** for over 30 minutes, **Mark paid** and **Mark failed** appear. Check the M-Pesa portal first.

- **Mark paid**: only when the portal shows the money reached the player. Enter the M-Pesa receipt and a note.
- **Mark failed**: only when the portal shows it did not go through. The money returns to the player's wallet.

Getting this wrong pays the player twice, or not at all.

### Resolve a deposit

*Reconcile payments.* Payments → Deposits, filter **Needs attention**.

| Status | Meaning | Action |
|---|---|---|
| Pending | STK prompt sent, no answer yet. The system asks M-Pesa every minute for 30 minutes. | **Check status** to ask now. |
| Mismatch | M-Pesa received a different amount than requested. | **Credit amount received** credits what M-Pesa actually received. |
| Unmatched | A Paybill payment whose account number matched no player. | **Assign to player**: search the player by phone, add a note. |
| — | A payment you've refunded in the M-Pesa portal, or a duplicate. | **Dismiss**, with a note: closes it without crediting anyone. |

### Daily reconciliation

*Reconcile payments.* Payments → Reconciliation.

- **Needs attention**: counts of deposits and withdrawals waiting for a person, each linking to the filtered list.
- **M-Pesa against the ledger (last 14 days)**: M-Pesa's deposit and payout totals beside the wallet ledger's. A deposit difference shows in red; investigate it before the day closes.
- **M-Pesa account balances**: **Check Paybill balance** / **Check B2C balance** ask Safaricom. The answer appears in the table when M-Pesa replies (usually within a minute; reload the page).

### Reports and exports

*View reports.* Reports. Pick **From** and **Until** (EAT days, up to 366 days).

- Sections: period summary, revenue by day, turnover breakdown, top players, payments by day, and tax by day and month.
- **Download tax report** → **CSV** or **Excel**.

*Export data.* **Export ledger** (Reports), **Export bets** (Bets), **Export deposits** (Deposits), **Export withdrawals** (Withdrawals).

- Exports follow the list's current filters.
- They run in the background and arrive in the notifications bell.
- Only the admin who started an export can download it. Files are deleted after 7 days.
- Exports contain phone numbers, so treat them as personal data.

### Publish a promotion

*Manage promotions.* Marketing → Promotions → **New promotion**:

- title;
- **Link** (the page address, `/promotions/{link}`);
- summary;
- body (Markdown);
- **Banner image**;
- **Starts** and **Ends** (EAT);
- **Published**;
- **Order**.

A promotion shows on the site only while it is published and inside its dates. **Open** previews the live page.

A promotion page is just a description. The offers that actually run (multibet bonus, jackpot, invite rewards) are coded and configured in `config/sportsbook.php`. Don't advertise terms the system doesn't apply.

---

## Audit trail

*View activity log.* Access → Activity Log records every staff action that changes something, with who, when, the reason entered and the values before and after:

- results and corrections;
- voids and cancellations;
- price overrides, market suspensions and reopenings;
- jackpot drafts, publishing, settlement and cancellation;
- player suspensions and balance adjustments;
- free bets given, voided or earned through invites;
- withdrawal decisions and deposit resolutions;
- M-Pesa balance checks;
- competition and promotion changes;
- admin sign-ins, role changes and account changes (password changes are logged without the password);
- exports (with the filters used) and tax report downloads;
- players' own deposit limits and breaks.

Reasons are required on almost every action because they end up here. Write them for the person who reads them in six months.
