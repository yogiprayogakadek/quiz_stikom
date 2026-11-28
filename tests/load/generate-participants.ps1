param(
    [ValidateRange(1, 10000)]
    [int]$Count = 100,

    [ValidatePattern('^[A-Za-z0-9_-]+$')]
    [string]$Prefix = 'loadtest',

    [Parameter(Mandatory = $true)]
    [ValidateLength(8, 255)]
    [string]$Password,

    [string]$OutputPath = '.\storage\app\private\loadtest-participants.csv'
)

if (Test-Path -LiteralPath $OutputPath) {
    throw "File sudah ada: $OutputPath"
}

$participants = 1..$Count | ForEach-Object {
    [PSCustomObject]@{
        nama = 'Peserta Uji Beban {0:D3}' -f $_
        username = "$Prefix$_"
        password = $Password
    }
}

$participants | Export-Csv -LiteralPath $OutputPath -NoTypeInformation -Encoding UTF8
Write-Output "Membuat $Count akun di $OutputPath"
