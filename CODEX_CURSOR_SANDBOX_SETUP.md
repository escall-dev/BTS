# Codex in Cursor: Windows Sandbox Setup Guide

This guide fixes the Windows restricted-token error that prevents Codex from reliably editing files in `C:\xampp\htdocs\BTS`.

## 1. Close Cursor completely

1. Save all files and close every Cursor window.
2. Check the Windows system tray and quit Cursor if it remains open.
3. Open Task Manager with `Ctrl + Shift + Esc`.
4. End any remaining Cursor process.

## 2. Back up the Codex configuration

Open PowerShell and run:

```powershell
Copy-Item `
  -LiteralPath "C:\Users\admin\.codex\config.toml" `
  -Destination "C:\Users\admin\.codex\config.backup.toml"
```

Verify the backup:

```powershell
Test-Path "C:\Users\admin\.codex\config.backup.toml"
```

Expected result: `True`.

To restore the backup later:

```powershell
Copy-Item `
  -LiteralPath "C:\Users\admin\.codex\config.backup.toml" `
  -Destination "C:\Users\admin\.codex\config.toml" `
  -Force
```

## 3. Edit `config.toml`

Run:

```powershell
notepad "C:\Users\admin\.codex\config.toml"
```

At the beginning of the file, before the first section with square brackets, add:

```toml
sandbox_mode = "workspace-write"
approval_policy = "on-request"
```

Find the existing Windows section:

```toml
[windows]
sandbox = "unelevated"
```

Change it to:

```toml
[windows]
sandbox = "elevated"
```

Important:

- Add `sandbox_mode` and `approval_policy` only once.
- Keep them outside `[windows]`, `[features]`, and other sections.
- Do not add a second `[windows]` section.
- Do not delete the existing model, notification, plugin, memory, or MCP settings.

## 4. Save and validate the configuration

Save the file in Notepad, then run:

```powershell
Select-String `
  -Path "C:\Users\admin\.codex\config.toml" `
  -Pattern "sandbox_mode|approval_policy|^\[windows\]|sandbox ="
```

Expected settings:

```text
sandbox_mode = "workspace-write"
approval_policy = "on-request"
[windows]
sandbox = "elevated"
```

Confirm that Notepad did not create `config.toml.txt`:

```powershell
Get-ChildItem "C:\Users\admin\.codex" -Filter "config*"
```

## 5. Start Cursor as Administrator

1. Open the Windows Start menu.
2. Search for Cursor.
3. Right-click Cursor and select **Run as administrator**.
4. Accept the Windows UAC prompt.
5. Approve any elevated-sandbox setup prompt shown by Codex.

Administrator access may only be required for the initial elevated-sandbox setup. After setup succeeds, test whether normal Cursor launches work.

## 6. Open only BTS

In Cursor:

1. Select **File > Open Folder**.
2. Open `C:\xampp\htdocs\BTS`.
3. Trust the folder if prompted.
4. Do not open all of `C:\xampp\htdocs` or use a multi-root workspace.

## 7. Start a new Codex chat

Create a new chat because the old conversation may retain the old sandbox profile. Send:

```text
Inspect the current workspace and report:
1. the workspace root,
2. the sandbox mode,
3. the Windows sandbox implementation,
4. whether C:\xampp\htdocs\BTS is writable.

Do not modify any files.
```

Expected result:

```text
Workspace root: C:\xampp\htdocs\BTS
Sandbox mode: workspace-write
Windows sandbox: elevated
BTS writable: yes
```

## 8. Test patching safely

In the new Codex chat, send:

```text
Use apply_patch to add a temporary comment to an existing BTS PHP file, verify the patch, and then remove the same comment using apply_patch. Do not change any application behavior.
```

The test passes when no `split writable root sets` error appears and the file finishes without a lasting change.

## 9. Install BTS Composer dependencies

Open PowerShell and run:

```powershell
Set-Location "C:\xampp\htdocs\BTS"
composer install
```

If `composer` is not recognized:

```powershell
& "C:\ProgramData\ComposerSetup\bin\composer.bat" install
```

Verify the local autoloader:

```powershell
Test-Path "C:\xampp\htdocs\BTS\vendor\autoload.php"
```

Expected result: `True`.

Verify PhpSpreadsheet:

```powershell
C:\xampp\php\php.exe -r "require 'C:/xampp/htdocs/BTS/vendor/autoload.php'; echo \PhpOffice\PhpSpreadsheet\Spreadsheet::class;"
```

Expected output:

```text
PhpOffice\PhpSpreadsheet\Spreadsheet
```

## If elevated mode fails

1. Close Cursor completely.
2. Open `C:\Users\admin\.codex\config.toml`.
3. Change the Windows setting back to:

```toml
[windows]
sandbox = "unelevated"
```

4. Save the file and restart Cursor normally.
5. Keep `sandbox_mode = "workspace-write"` and `approval_policy = "on-request"`.
6. Approve narrowly scoped file-edit requests when required.

Common elevated-sandbox blockers include denied UAC permission, blocked local user/group creation, blocked firewall rules, missing sandbox-user logon rights, antivirus rules, or company policy.

Avoid `danger-full-access` unless the machine is fully trusted and unrestricted filesystem access is genuinely necessary.

## Recommended final configuration

```toml
sandbox_mode = "workspace-write"
approval_policy = "on-request"

[windows]
sandbox = "elevated"
```

This keeps Codex restricted to the BTS workspace while using the recommended Windows sandbox implementation.
