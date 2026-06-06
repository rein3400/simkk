# SIM-KK Black Box Test Helpers
# Import this from each test script with `. "$PSScriptRoot\00-helpers.ps1"`

$BaseUrl = "http://127.0.0.1:8000"
$DefaultPassword = "simkk-2026"

$Script:Results = @()
$Script:StartTime = Get-Date

function Reset-Results {
    $Script:Results = @()
    $Script:StartTime = Get-Date
}

function Add-Result {
    param(
        [string]$Suite,
        [string]$Scenario,
        [string]$Expected,
        [string]$Actual,
        [bool]$Pass,
        [string]$Note = "",
        [string]$Severity = "Info"
    )
    $Script:Results += [PSCustomObject]@{
        suite     = $Suite
        scenario  = $Scenario
        expected  = $Expected
        actual    = $Actual
        pass      = $Pass
        note      = $Note
        severity  = $Severity
        timestamp = (Get-Date).ToString("o")
    }
    $status = if ($Pass) { "PASS" } else { "FAIL" }
    Write-Host "  [$status] $Scenario :: $Expected vs $Actual :: $Note" -ForegroundColor $(if($Pass){"Green"}else{"Red"})
}

function Invoke-Api {
    param(
        [string]$Method = "GET",
        [string]$Path,
        [hashtable]$Headers = @{},
        [object]$Body = $null,
        [string]$FormField = $null,
        [string]$FilePath = $null,
        [string]$FieldName = "photo"
    )
    $url = "$BaseUrl$Path"
    $params = @{
        Method      = $Method
        Uri         = $url
        Headers     = $Headers
        TimeoutSec  = 30
    }
    if ($FormField -and $FilePath) {
        $form = @{
            $FieldName = Get-Item -Path $FilePath
        }
        if ($Body) {
            foreach ($k in $Body.Keys) { $form[$k] = $Body[$k] }
        }
        $params["Form"] = $form
    } elseif ($Body) {
        $params["Body"] = ($Body | ConvertTo-Json -Depth 10)
        $params["ContentType"] = "application/json"
    }
    try {
        $resp = Invoke-WebRequest @params -UseBasicParsing -ErrorAction Stop
        return @{
            status  = [int]$resp.StatusCode
            body    = $resp.Content
            headers = $resp.Headers
            json    = $null
        }
    } catch {
        $code = 0
        $body = ""
        if ($_.Exception.Response) {
            $code = [int]$_.Exception.Response.StatusCode
            try { $body = (New-Object IO.StreamReader($_.Exception.Response.GetResponseStream())).ReadToEnd() } catch {}
        }
        return @{ status = $code; body = $body; headers = $null; json = $null; error = $_.Exception.Message }
    }
}

function Get-LoginToken {
    param(
        [string]$Username,
        [string]$Password = $DefaultPassword,
        [string]$Level = $null
    )
    $body = @{ username = $Username; password = $Password }
    if ($Level) {
        # API expects case-sensitive role names: Kasir, Terapis, Gudang, Manajer
        $normalized = (Get-Culture).TextInfo.ToTitleCase($Level.ToLower())
        $body["level"] = $normalized
    }
    $r = Invoke-Api -Method POST -Path "/api/login" -Body $body
    try {
        $j = $r.body | ConvertFrom-Json -ErrorAction SilentlyContinue
        if ($j.token) { return @{ token = $j.token; user = $j.user; raw = $r } }
    } catch {}
    return @{ token = $null; raw = $r }
}

function Get-AuthHeader {
    param([string]$Token)
    if (-not $Token) { return @{} }
    return @{ Authorization = "Bearer $Token" }
}

function Save-Results {
    param([string]$Path)
    $Script:Results | ConvertTo-Json -Depth 10 | Out-File -FilePath $Path -Encoding utf8
}
