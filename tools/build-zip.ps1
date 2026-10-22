# Build an installable Livecrafts plugin zip.
#
# WHY THIS EXISTS, AND WHY NOT Compress-Archive:
#   PowerShell's Compress-Archive writes entry names with BACKSLASHES ("livecrafts\livecrafts.php"). The zip
#   format wants forward slashes, and WordPress's installer treats a backslash as part of the FILENAME, not as a
#   folder. The plugin then unpacks as a flat heap of oddly named files, or the upload is simply rejected - which
#   looks exactly like "I zipped it again and nothing happened" or "I can't install the zip she sent me".
#   Windows Explorer's own "Send to > Compressed (zipped) folder" is fine; Compress-Archive is not.
#
#   It also excludes templates-lib/node_modules, which is in the repository but has never been shipped - it is
#   ~570 files and three times the size of the whole plugin.
#
# Usage, from anywhere:   powershell -ExecutionPolicy Bypass -File tools\build-zip.ps1
#                         powershell -ExecutionPolicy Bypass -File tools\build-zip.ps1 -OutDir D:\Hackathon
[CmdletBinding()]
param(
  # Where to write the zip. Default: the repository's parent, beside the earlier releases.
  [string]$OutDir = ""
)

$ErrorActionPreference = "Stop"
Add-Type -AssemblyName System.IO.Compression, System.IO.Compression.FileSystem

$repo = Split-Path -Parent $PSScriptRoot
$src = Join-Path $repo "livecrafts"
if (-not (Test-Path (Join-Path $src "livecrafts.php"))) { throw "livecrafts\livecrafts.php not found under $repo - is this the plugin repository?" }
if (-not $OutDir) { $OutDir = Split-Path -Parent $repo }

# The version the plugin declares is the version we ship, so the two can never disagree.
$header = (Get-Content (Join-Path $src "livecrafts.php") -TotalCount 20) -join "`n"
if ($header -notmatch '(?m)^\s*\*\s*Version:\s*(.+?)\s*$') { throw "No 'Version:' line in the plugin header." }
$version = $Matches[1]

$out = Join-Path $OutDir "livecrafts-$version.zip"
if (Test-Path $out) { Remove-Item -Force $out }

# Everything the plugin needs, and nothing the repository keeps for itself.
$skip = @("node_modules", ".git", ".github", "tests", "tester")
$files = Get-ChildItem -Recurse -File $src | Where-Object {
  $rel = $_.FullName.Substring($src.Length).TrimStart('\')
  $parts = $rel -split '\\'
  -not ($parts | Where-Object { $skip -contains $_ }) -and $_.Name -notin @(".DS_Store", "Thumbs.db")
} | Sort-Object FullName

$zip = [System.IO.Compression.ZipFile]::Open($out, [System.IO.Compression.ZipArchiveMode]::Create)
try {
  foreach ($f in $files) {
    # Forward slashes, and the plugin folder as the single root - what WordPress expects to unpack.
    $entry = "livecrafts/" + ($f.FullName.Substring($src.Length).TrimStart('\') -replace '\\', '/')
    [void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $f.FullName, $entry, [System.IO.Compression.CompressionLevel]::Optimal)
  }
} finally { $zip.Dispose() }

$kb = [math]::Round((Get-Item $out).Length / 1KB, 1)
Write-Host "Built $out"
Write-Host "  version $version · $($files.Count) files · $kb KB"
Write-Host ""
Write-Host "To install over an existing copy, WordPress needs the old one gone first:"
Write-Host "  Plugins > Livecrafts > Deactivate, then Delete, then Add New > Upload Plugin."
Write-Host "  (Deleting the plugin does not touch its settings or your drafts - those live in the database.)"
