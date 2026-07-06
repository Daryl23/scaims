$ErrorActionPreference = "Stop"

$baseDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$files = @(
    @{
        Docx = Join-Path $baseDir "SCAIMS_User_Manual_English.docx"
        Pdf = Join-Path $baseDir "SCAIMS_User_Manual_English.pdf"
    },
    @{
        Docx = Join-Path $baseDir "SCAIMS_User_Manual_Tagalog.docx"
        Pdf = Join-Path $baseDir "SCAIMS_User_Manual_Tagalog.pdf"
    }
)

$hadError = $false

foreach ($file in $files) {
    if (-not (Test-Path $file.Docx)) {
        throw "Missing DOCX file: $($file.Docx)"
    }

    $word = $null
    $document = $null

    try {
        $word = New-Object -ComObject Word.Application
        $word.Visible = $false
        $word.DisplayAlerts = 0

        $document = $word.Documents.Open($file.Docx, $false, $true)
        try {
            $document.ExportAsFixedFormat($file.Pdf, 17)
        }
        catch {
            $formatPdf = 17
            $document.SaveAs([ref]$file.Pdf, [ref]$formatPdf)
        }
        Write-Output $file.Pdf
    }
    catch {
        $hadError = $true
        Write-Error "Failed to export $($file.Docx): $($_.Exception.Message)"
    }
    finally {
        if ($document -ne $null) {
            try {
                $document.Close(0)
            }
            catch {
                Write-Warning "Word document cleanup failed: $($_.Exception.Message)"
            }
            try {
                [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($document)
            }
            catch {
                Write-Warning "Word document COM release failed: $($_.Exception.Message)"
            }
        }

        if ($word -ne $null) {
            try {
                $word.Quit()
            }
            catch {
                Write-Warning "Word application cleanup failed: $($_.Exception.Message)"
            }
            try {
                [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($word)
            }
            catch {
                Write-Warning "Word application COM release failed: $($_.Exception.Message)"
            }
        }

        [GC]::Collect()
        [GC]::WaitForPendingFinalizers()
        Start-Sleep -Milliseconds 500
    }
}

if ($hadError) {
    exit 1
}
