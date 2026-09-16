# Afrostrength contractors

A checked contractor directory and job board for Afrostrength Limited, Lagos.

PHP 8.1+, MariaDB, no Node at runtime. Built to run on cPanel shared hosting
without SSH, like the academy.

## Why this is its own repository

It is a different product from Afrotech Academy, and the separation is
deliberate rather than tidy-minded.

A learner and a contractor are **different data subjects held on different
lawful bases**: a learner's record exists to teach them, a contractor's to put
them in front of paying clients. Under the Nigeria Data Protection Act 2023,
merging the two databases would mean one consent had to cover both purposes,
which it does not. It also means a bug in a job board cannot read somebody's
marks.

So: its own schema, its own staff accounts, its own deployment. The shared
libraries (`bootstrap`, `db`, `guards`, `assets`, `ui`, `money`, `migrate`)
are **copied** from the academy rather than imported, because a shared
dependency between two products on two hosts is a coupling neither wants.

## The two rules the product rests on

**Nothing is listed until somebody has checked it.** The entire value of the
directory is that being on it means something; an unchecked one is a phone
book. And a bad introduction costs Afrostrength the next client, not the
contractor who oversold themselves. Enforced in the SQL `WHERE`, not in a
template that could forget.

**A client's contact details are never shown to contractors.** Not hidden in
the template — absent from the query that builds the job board. If they leaked,
the board would be a free lead list and the introduction fee would be
unenforceable. That fee is the business.

## What it does not do

**No escrow, and no money moves through this system.** Holding other people's
funds in Nigeria raises CBN licensing questions that are a legal matter rather
than an engineering one. A client pays their contractor directly; Afrostrength
invoices its introduction fee separately. The ledger records what is owed and
what was paid, against a bank reference.

## Layout

```
lib/              the application. OUTSIDE the web root on a real host.
  config.php      settings and the database password. NEVER committed.
migrations/       schema, applied from the ops page
public/           the document root
  index.php       the directory
  contractor.php  one listing
  join/           contractors put themselves forward
  post/           clients post a job
  work/           the job board, client details absent
  console/        Afrostrength staff
  _ops.php        the checks, behind ops_token
var/              logs and rate-limit state. Deny-all.
```

## Installing

1. Upload `lib/`, `migrations/` and `var/` **above** the web root; `public/`
   contents into it.
2. Create a database and a user. Copy `lib/config.example.php` to
   `lib/config.php`, fill it in, `chmod 600`.
3. Open `/_ops.php?token=YOUR_OPS_TOKEN`. Run the migrations. Create the first
   administrator — the password is shown once and is never emailed.
4. Sign in at `/console/signin.php`.

There is no default password anywhere in this software.

## Status

First version: directory, job board, introductions and the fee ledger.
Verified end to end against a real MariaDB — see the commit message.

Not yet built: email notifications (no PHPMailer vendored; the autoloader is
optional), contractor self-service editing, reviews.
