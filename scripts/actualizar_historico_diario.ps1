$ErrorActionPreference = 'Stop'
$projectDirectory = Split-Path -Parent $PSScriptRoot
$phpExecutable = 'C:\xampp\php\php.exe'
$historyScript = Join-Path $PSScriptRoot 'actualizar_historico_diario.php'
$logFile = Join-Path $projectDirectory 'storage\cache\history-daily.log'
Set-Location -LiteralPath $projectDirectory
$logDirectory = Split-Path -Parent $logFile
if (-not (Test-Path -LiteralPath $logDirectory)) {
    New-Item -ItemType Directory -Path $logDirectory -Force | Out-Null
}
$utf8Encoding = New-Object System.Text.UTF8Encoding($false)
[Console]::OutputEncoding = $utf8Encoding
$OutputEncoding = $utf8Encoding
if (Test-Path -LiteralPath $logFile) {
    $existingBytes = [System.IO.File]::ReadAllBytes($logFile)
    if ($existingBytes.Length -ge 2 -and $existingBytes[0] -eq 255 -and $existingBytes[1] -eq 254) {
        $legacyLog = Join-Path $logDirectory ('history-daily-legacy-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.log')
        Move-Item -LiteralPath $logFile -Destination $legacyLog
    }
}
Add-Content -LiteralPath $logFile -Encoding UTF8 -Value ("[{0}] Inicio de actualizacion" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'))
# Windows PowerShell treats native stderr as an error; keep it in the log
# without stopping before PHP's exit code and remaining output are collected.
$ErrorActionPreference = 'Continue'
& $phpExecutable $historyScript 2>&1 | ForEach-Object {
    [System.IO.File]::AppendAllText($logFile, $_.ToString() + [Environment]::NewLine, $utf8Encoding)
}
$historyExitCode = $LASTEXITCODE
$ErrorActionPreference = 'Stop'
Add-Content -LiteralPath $logFile -Encoding UTF8 -Value ("[{0}] Fin de actualizacion; codigo {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $historyExitCode)
exit $historyExitCode
