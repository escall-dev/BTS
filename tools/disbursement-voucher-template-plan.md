# Apply Disbursement Voucher Sheet Template

## Summary

Map `disbursement-voucher` to the workbook sheet named `Disbursement Voucher`. Build the Disbursement Voucher input screen from that sheet's placeholders, not from the `Purchase Order` sheet.

Fields filled in the Disbursement Voucher workflow section should replace placeholders on the `Disbursement Voucher` sheet, such as `${DIVNO}`, `${PAYEE}`, `${DDFUND}`, `${PAYEE_ADDRESS}`, `${TIN}`, `${PARTICULARS}`, `${GROSS}`, `${WHTAX}`, and `${NETAMOUNT}`.

## Key Changes

- Add `disbursement-voucher` to `TemplateMapping` with sheet `Disbursement Voucher`.
- Use these DV transaction fields on the workflow form:
  - `DIVNO`: DV No.
  - `DATET`: Date
  - `PAYEE`: Payee
  - `DDFUND`: Fund
  - `PAYEE_ADDRESS`: Payee Address
  - `TIN`: TIN
  - `PARTICULARS`: Particulars
  - `GROSS`: Gross Amount
  - `WHTAX`: W/H Tax
  - `NETAMOUNT`: Net Amount
- Render `DDFUND` as a dropdown.
- Store fund choices on System Input as one editable `Fund Options` row, parsed by newline or comma.
- Auto-create missing system placeholders on System Input:
  - `APPROPRIATION_CHAIRPERSON`
  - `BARANGAY_BOOKKEEPER`
  - `CITY_ACCOUNTANT`
  - plus any other system-style placeholders not already defined.

## Amount Behavior

- User enters `GROSS` and `WHTAX`.
- `NETAMOUNT` is readonly and calculated as `GROSS - WHTAX`.
- Save the calculated `NETAMOUNT` so `${NETAMOUNT}` is replaced in the generated sheet.

## Template Generation

- Load only the `Disbursement Voucher` sheet for this step.
- Replace `${KEY}` / `{KEY}` placeholders using merged workflow data and system inputs.
- Keep the step label as `Disbursement Voucher`.
- Do not write to or depend on the `Purchase Order` sheet.

## Test Plan

- Confirm System Input shows fund options and missing DV system placeholders.
- Confirm DV form shows `DDFUND` as a dropdown.
- Save DV and verify all values stay visible after save.
- Generate or print DV and confirm the output uses the `Disbursement Voucher` sheet.
- Verify placeholders are replaced correctly.
- Run `php -l` on touched PHP files.

## Assumptions

- `NETAMOUNT = GROSS - WHTAX`.
- Fund options are managed through one System Input row named `Fund Options`.
- Placeholders tied to signatories or office data belong on System Input.
- Payee, DV number, date, particulars, and amounts belong on the DV workflow form.
