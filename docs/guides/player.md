# Player guide

How the player site works, from sign-up to withdrawal. Amounts and limits are the defaults in `config/sportsbook.php`; the ones marked *(env)* can be changed in `.env` without a code change.

All times are East Africa Time (EAT). All money is Kenyan shillings (KES).

- [Accounts](#accounts)
- [Finding matches](#finding-matches)
- [The bet slip](#the-bet-slip)
- [Wallet: deposits and withdrawals](#wallet-deposits-and-withdrawals)
- [My bets](#my-bets)
- [Jackpot](#jackpot)
- [Promotions, free bets and invites](#promotions-free-bets-and-invites)
- [Responsible gambling](#responsible-gambling)
- [Account settings](#account-settings)
- [Account states](#account-states)
- [Mobile app API](#mobile-app-api)

---

## Accounts

### Sign up (`/register`)

1. Enter a Kenyan phone number (07…, 01… or +254…) and tap **Send code**.
2. We text a 6-digit code. It lasts 10 minutes. You get 5 tries, and you can tap **Resend code** after 60 seconds.
3. Enter the code, a password and, optionally, a friend's **Invite code**. Opening a friend's invite link (`/register?ref=CODE`) fills in the code for you.
4. Tap **Create account**. You must be 18 or older and accept the Terms & Conditions.

The phone number is your login. It is also the M-Pesa number: deposits are charged to it and withdrawals are paid to it.

Each number can receive at most 5 codes an hour, and each IP address 20 an hour. Every SMS costs money, so these caps stop abuse.

In production, passwords need at least 12 characters, with upper and lower case letters, a number and a symbol, and they must not appear in a known data breach.

### Log in (`/login`)

Enter your phone number and password. If two-factor authentication is on, you also enter the 6-digit code from your authenticator app or a recovery code. If you've added a passkey, you can log in with it instead of the password.

### Forgot password (`/forgot-password`)

Enter your phone number. We text a code; enter it with a new password. A reset logs out every mobile app device.

---

## Finding matches

| Where | What it shows |
|---|---|
| Home (`/`) | Tabs: **Highlights**, **Today**, **Tomorrow**, **Upcoming**. A market switch changes which odds each row shows. The first 20 matches load at once, and more load as you scroll (or tap **Show more matches**). |
| Sports sidebar / **Sports** menu on phones | Competitions grouped by sport. Each opens `/competitions/{id}`. |
| Live (`/live`) | Matches in play. Live betting and live scores are not available yet: markets close at kick-off. |
| Search (`/search`) | Find a match by team or competition. |
| Jackpot, Promotions | See below. |
| Virtuals, Crash | "Coming soon" pages. |

Only competitions that staff have switched on ("synced") appear. Odds come from The Odds API, with a margin added: 6% on the Main tab markets (1X2, Double Chance, Draw No Bet) and 10% on the markets priced by our own goals model (Goals, Correct Score, Half-time/Full-time).

### Match details (the match panel)

Tap a match name, or **+N markets**, in any list. A panel slides in from the right with every market for that match, grouped into tabs:

| Tab | Markets |
|---|---|
| Main | 1X2, Double Chance, Draw No Bet |
| Goals | Over/Under, Both Teams To Score |
| Correct Score | Correct Score |
| Half-time | Half-time / Full-time |

- The page behind the panel stays put. Close the panel with **Close**, Escape, the phone's Back button, or a tap on the dark area beside it.
- On a phone, the panel fills the screen and has a **Bet slip** button at the bottom showing how many picks you have.
- On a desktop, the panel opens beside the bet slip, so both stay visible.
- **Full page** opens the same details at `/matches/{id}`, a link you can share. Ctrl-click or middle-click a match also opens the full page in a new tab.

A market can close while you look at it. Markets close at kick-off, when fewer than 3 bookmakers price the match, when odds are more than 26 hours old, or when staff suspend them. Closed odds can't be added to the slip.

---

## The bet slip

On a desktop the slip is the right-hand column. On a phone, tap **Betslip** in the bottom bar.

### Adding picks

- Tap any odds button to add it. Tap it again to remove it.
- You can have one pick per match. Tapping another outcome from the same match replaces the first.
- At most 30 picks *(env: `SPORTSBOOK_MAX_SELECTIONS`)*.
- The pick shows at once. The slip then checks the current odds with the server ("Checking odds…").
- One pick makes a single; two or more make a multibet (accumulator): every pick must win, and the odds multiply.

### Staking and placing

1. Enter a **Stake (KES)**, or tap a quick-stake button. Stakes run from KES 10 to KES 20,000 *(env: `SPORTSBOOK_MIN_STAKE`, `SPORTSBOOK_MAX_STAKE`)*.
2. The slip shows the total **Odds**, any **Multibet bonus**, and **Possible win (before tax)**. The most a bet can pay is KES 1,000,000 *(env: `SPORTSBOOK_MAX_PAYOUT`)*.
3. Tap **Place bet**. You get a **Bet ID**, and the bet appears in **My bets**.

You must be logged in (**Log in to bet**) and have enough balance (**Deposit now** shows if you don't).

### Odds changes

Odds can move between adding a pick and placing the bet. Under **Odds changes** you choose what happens. The choice is saved to your account.

| Setting | Effect |
|---|---|
| Accept higher odds only (default) | The bet goes through if every price is the same or higher. |
| Accept any odds changes | The bet always goes through at the current prices. |
| Do not accept odds changes | The bet stops if any price moved. |

When a change stops the bet, the slip says **Some odds have changed since you added them.** Tap **Accept odds changes** to continue at the new prices. Picks whose market closed are marked; **Remove closed selections** clears them.

The bet is always placed at the current price. The setting only decides whether a change stops it.

### Booking codes

- **Get booking code** saves your slip as a 6-character code you can **Share** or copy. It holds only picks that are open now, one per match, and expires when the first of those matches kicks off.
- **Have a booking code?** Type a code and tap **Load** to fill your slip. Picks that have since closed are listed as "No longer available, so not added".

### Using a free bet

If you have free bets, the slip lists them under **Free bets**. Choose one, and its whole amount becomes the stake (**Free bet stake**); you can't change the amount. Tap **Place free bet**. If it wins, you get the winnings minus the stake, as cash. **Pay with cash** switches back to a normal stake.

---

## Wallet: deposits and withdrawals

Open **Wallet** (`/account/wallet`). It shows your **Balance**, deposit and withdrawal forms, **Free bets**, your invite link, a link to **Limits & breaks**, and your **Transactions**.

### Deposit with M-Pesa (STK push)

1. Enter an amount from KES 10 to KES 150,000 *(env: `SPORTSBOOK_MIN_DEPOSIT`, `SPORTSBOOK_MAX_DEPOSIT`)* and tap **Deposit**.
2. Your phone shows an M-Pesa prompt. Enter your M-Pesa PIN.
3. The page waits (**Check your phone**) and updates when M-Pesa confirms. You can leave the page; the money arrives either way.

What can happen next:

| Message | Meaning |
|---|---|
| Balance goes up | Paid. |
| Deposit cancelled | You cancelled on the phone. No money was taken. |
| Deposit not completed | M-Pesa did not complete it, for example because of a wrong PIN or a timeout. No money was taken. |
| Still waiting for M-Pesa | No answer yet. The system asks M-Pesa itself after about a minute and keeps trying for up to 30 minutes. |
| Deposit under review | M-Pesa reported a different amount than you asked for. Staff will check it and credit you. |

You can request at most 5 prompts every 10 minutes.

### Deposit through the M-Pesa menu (Paybill)

Under **Or pay through the M-Pesa menu**: Lipa na M-Pesa → Pay Bill → the business number shown → account number: **your phone number** → amount. The deposit is credited when M-Pesa confirms it. If the account number doesn't match a player, staff assign the payment by hand.

### Withdraw to M-Pesa

1. Enter an amount from KES 50 to KES 150,000 *(env: `SPORTSBOOK_MIN_WITHDRAWAL`, `SPORTSBOOK_MAX_WITHDRAWAL`)* and tap **Withdraw**. The money leaves your balance straight away.
2. Under KES 20,000 *(env: `SPORTSBOOK_WITHDRAWAL_APPROVAL_THRESHOLD`)*, it is sent to your phone automatically. At KES 20,000 or more, a staff member approves it first.
3. If the payout fails or staff reject it, the full amount returns to your balance.

You can make at most 5 withdrawals an hour. Free-bet credit can't be withdrawn: only winnings from free bets become cash.

### Taxes

Tax rates are set to 0 until the operator confirms them *(env: `SPORTSBOOK_DEPOSIT_TAX_RATE`, `SPORTSBOOK_WINNINGS_TAX_RATE`)*. When set, winnings tax applies to profit (winnings minus stake). That's why the slip says "Possible win (before tax)".

---

## My bets

`/my-bets` has three tabs:

- **Open bets**: bets waiting on at least one result.
- **Settled**: won, lost, void or cancelled bets. A bet moves here once every match on it has a result.
- **Jackpot**: your jackpot entries, with how each pick did.

Each bet shows its Bet ID, stake (or **Free bet stake**), odds, possible win, and each pick's result.

### How bets settle

- Results come from the scores feed about 2¼ hours after kick-off. UEFA Champions League matches are settled by staff.
- A **void** pick counts as odds 1.00. A single on a void pick refunds the stake.
- A pick on a match moved by more than 48 hours, or abandoned, is voided.
- If staff correct a wrong result, bets settled on it are settled again. Anything they paid is taken back and the new amount paid.
- Staff can void a single pick that was priced in error, or cancel an open bet (stake refunded).

---

## Jackpot

`/jackpot` shows the open jackpot: usually 17 matches (between 13 and 17).

1. Pick 1, X or 2 for every match, or tap **Auto-pick** to fill in random picks.
2. Tap **Enter for KES 99** to buy an entry. You see **You are in!** with an Entry ID. **Enter again** buys another entry.
3. Entries close when the first match kicks off. A countdown shows how long is left (**Entries close in**).

Prizes (defaults; staff set them per jackpot):

| Right | Prize pool |
|---|---|
| 17 | KES 1,000,000 |
| 16 | KES 100,000 |
| 15 | KES 20,000 |

Each pool is shared equally by every entry with that many right. A void match counts as right for everyone. If more than 3 matches are void, the jackpot is cancelled and every entry is refunded.

---

## Promotions, free bets and invites

`/promotions` lists the offers staff publish, and each has its own page (`/promotions/{link}`). The offers the system actually runs are:

### Multibet bonus

Put 3 or more picks priced 1.30 or more on one bet. If it wins, a bonus is added to your profit:

| Picks at 1.30+ | 3 | 4 | 5 | 6 | 8 | 10 | 15 | 20+ |
|---|---|---|---|---|---|---|---|---|
| Bonus | 3% | 5% | 7% | 10% | 15% | 20% | 35% | 50% |

Only winning picks at 1.30 or more count; void picks don't. The bonus is never more than the bet qualified for when you placed it. There is no bonus on free bets.

### Free bets

Staff give free bets, and you earn them by inviting friends. Each free bet has a minimum total odds (usually 1.50) and an expiry date (usually 7 days). Find yours under **Free bets** on the wallet page and in the slip.

### Invite friends

Your invite code and link are on the wallet page (**Invite friends**, **Copy link**).

1. Your friend signs up with your link or types your code.
2. Within 30 days, your friend deposits at least KES 100 and stakes at least KES 200 of their own money. Free bets and refunded bets don't count.
3. You get a KES 100 free bet: odds 1.50 or more, valid 7 days.

You can earn at most 10 rewards a calendar month. You get no reward while your account is suspended or on a break. Staff can turn invites off *(env: `REFERRALS_ENABLED`)*.

---

## Responsible gambling

`/account/responsible-gambling`, also the third tab in **Account settings**.

### Deposit limits

Set a **Daily (last 24 hours)**, **Weekly (last 7 days)** and/or **Monthly (last 30 days)** limit. The periods roll: they count back from now, not from midnight or the 1st of the month.

- Lowering a limit takes effect at once.
- Raising or removing a limit takes effect after 24 hours.

### Take a break

Before you start a break, you confirm it with your password.

| Type | Lengths |
|---|---|
| Cool-off | 24 hours, 7 days, 30 days |
| Self-exclusion | 6 months, 1 year, 5 years |

During a break you can log in and withdraw your balance, but you can't deposit or bet. A break can be made longer but never shorter, and nobody can end it early, staff included.

---

## Account settings

**Account settings** (avatar menu, or `/settings`) has three tabs:

| Tab | What you can do |
|---|---|
| Profile | Change your phone number (confirmed by an SMS code to the new number), change your name and email, delete your account. |
| Security | Change password. Turn two-factor authentication on or off: scan a QR code with an authenticator app, confirm a code, and save the recovery codes (**Show codes**, **Make new codes**). Add or remove passkeys (log in with your fingerprint, face or PIN). |
| Responsible gambling | Deposit limits and breaks, as above. |

Turning 2FA on or off, and adding a passkey, ask for your password first.

The site is dark-themed only.

---

## Account states

| State | Log in | See wallet | Deposit | Bet | Withdraw |
|---|---|---|---|---|---|
| Active | Yes | Yes | Yes | Yes | Yes |
| On a break (cool-off or self-exclusion) | Yes | Yes | No | No | Yes |
| Suspended by staff | Yes | Yes | No | No | No |

Suspended players see "Your account is suspended" and are asked to contact support.

---

## Mobile app API

A JSON API at `/api/v1` mirrors the player site for a future mobile app: sign-up, login, fixtures, slip quotes, bets, booking codes, wallet, deposits, withdrawals, limits, breaks, jackpot, promotions and invites. Each device logs in with its own token, which lasts 90 days. Requests are capped at 60 a minute.

The OpenAPI spec and a Postman collection are in [`docs/api/`](../api/).
