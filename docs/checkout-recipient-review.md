# Product checkout recipient review

Status: **unresolved; transfers remain disabled**.

The user confirmed the signature/Explorer link is unavailable and requested the UI be finished without enabling transfers. The previously supplied public recipient is retained as inactive configuration in `.env`; the fixed amount remains 1,000,000 lamports. Retaining those values is not approval of the recipient or permission to bypass the review. Neither a connection nor an enabled browser button can unlock the backend guards.

Read-only inspection of the local SQLite data found two demo orders, both cancelled. Both snapshot the public recipient previously supplied by the user and 1,000,000 lamports (0.001 SOL). Neither has a signature or retained signed transaction. These records cannot establish whether an external transaction occurred or identify its actual recipient. No record was edited, and no RPC submission, signature request or transfer was performed during this review.

The transaction signature or Devnet Explorer link for the reported mismatch is needed to compare the actual System Program transfer destination with the intended recipient. A cancelled local order is not proof of a successful or failed on-chain transfer. No root cause is claimed without that evidence.

Code review found a possible display/configuration inconsistency: a pending order retains its immutable recipient and amount, while the former Blade demo panel displayed current configuration. Reusing a pending order after configuration changes could therefore show a different recipient before approval. This is a potential cause, not a verified explanation of the reported mismatch.

Before any future reactivation:

1. Review the specific transaction on Devnet, including destination, source, amount, memo and execution result.
2. Explicitly confirm the intended recipient. Do not silently reuse a recipient from old records or configure a new/unknown address.
3. Make the displayed review details, server-issued order, locally built transaction and decoded signed transaction agree on recipient, exact lamports, memo, payer and network before approval and submission. Block mismatches rather than rewriting an existing signed order.
4. Retain finalized backend verification, signature uniqueness, order ownership, timestamp/blockhash checks, amount/balance checks and the fixed Devnet endpoint/genesis guard.
5. Re-run the security/regression suites and perform an explicit manual wallet approval only after the review is resolved. Never treat wallet connection, transaction signing, or submission as payment confirmation.

The current product checkout is a localized design and public-address connection flow only. Continue to Payment is disabled, including after connection; there is no signing/submission/verification module in its frontend dependency tree. Existing payment routes remain absent, and controller/service/RPC presentation guards remain enabled. The original backend verification code, historical records and archived payment tests are preserved for future work. The homepage/navigation remain wallet-free.
