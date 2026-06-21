# Generates favicons + a 1200x630 OG social card from the brand logo.
# Run from repo root:  powershell -ExecutionPolicy Bypass -File scripts\gen_brand_assets.ps1
Add-Type -AssemblyName System.Drawing

$root   = Split-Path $PSScriptRoot -Parent
$imgDir = Join-Path $root 'images'
$logoPath = Join-Path $imgDir 'logo.png'
$logo = [System.Drawing.Image]::FromFile($logoPath)

$navy   = [System.Drawing.Color]::FromArgb(255, 5, 16, 31)    # #05101F
$navy2  = [System.Drawing.Color]::FromArgb(255, 11, 28, 52)   # #0B1C34
$gold   = [System.Drawing.Color]::FromArgb(255, 229, 165, 10) # #E5A50A
$white  = [System.Drawing.Color]::FromArgb(255, 242, 246, 251)

function New-Canvas([int]$w, [int]$h) {
  $bmp = New-Object System.Drawing.Bitmap $w, $h
  $g = [System.Drawing.Graphics]::FromImage($bmp)
  $g.SmoothingMode     = 'AntiAlias'
  $g.InterpolationMode = 'HighQualityBicubic'
  $g.PixelOffsetMode   = 'HighQuality'
  $g.TextRenderingHint = 'AntiAliasGridFit'
  ,@($bmp, $g)
}

# --- Square favicon/app icons: logo centered on navy, scaled to fit ~78% ---
function Save-Icon([int]$size, [string]$file) {
  $c = New-Canvas $size $size; $bmp = $c[0]; $g = $c[1]
  $g.Clear($navy)
  $pad = [int]($size * 0.11)
  $box = $size - 2 * $pad
  $scale = [Math]::Min($box / $logo.Width, $box / $logo.Height)
  $w = [int]($logo.Width * $scale); $h = [int]($logo.Height * $scale)
  $x = [int](($size - $w) / 2); $y = [int](($size - $h) / 2)
  $g.DrawImage($logo, $x, $y, $w, $h)
  $g.Dispose()
  $bmp.Save((Join-Path $imgDir $file), [System.Drawing.Imaging.ImageFormat]::Png)
  $bmp
}

$b16  = Save-Icon 16  'favicon-16.png'
$b32  = Save-Icon 32  'favicon-32.png'
Save-Icon 180 'apple-touch-icon.png' | Out-Null
Save-Icon 192 'icon-192.png'         | Out-Null
Save-Icon 512 'icon-512.png'         | Out-Null

# --- favicon.ico from the 32px bitmap ---
$hicon = $b32.GetHicon()
$icon  = [System.Drawing.Icon]::FromHandle($hicon)
$fs = New-Object System.IO.FileStream((Join-Path $imgDir 'favicon.ico'), 'Create')
$icon.Save($fs); $fs.Close()
$b16.Dispose(); $b32.Dispose()

# --- 1200x630 OG / Twitter social card ---
$c = New-Canvas 1200 630; $bmp = $c[0]; $g = $c[1]
$rect = New-Object System.Drawing.Rectangle 0, 0, 1200, 630
$grad = New-Object System.Drawing.Drawing2D.LinearGradientBrush $rect, $navy, $navy2, 35.0
$g.FillRectangle($grad, $rect)

# top gold accent bar
$goldBrush = New-Object System.Drawing.SolidBrush $gold
$g.FillRectangle($goldBrush, 0, 0, 1200, 8)

# logo centered, upper area
$lw = 560.0
$ls = $lw / $logo.Width
$lh = $logo.Height * $ls
$lx = (1200 - $lw) / 2
$ly = 150.0
$g.DrawImage($logo, $lx, $ly, $lw, $lh)

# tagline
$titleFont = New-Object System.Drawing.Font 'Georgia', 40, ([System.Drawing.FontStyle]::Bold)
$subFont   = New-Object System.Drawing.Font 'Segoe UI', 24, ([System.Drawing.FontStyle]::Regular)
$fmt = New-Object System.Drawing.StringFormat
$fmt.Alignment = 'Center'
$whiteBrush = New-Object System.Drawing.SolidBrush $white
$greyBrush  = New-Object System.Drawing.SolidBrush ([System.Drawing.Color]::FromArgb(255,159,176,195))

$dot = [char]0x00B7
$cardRect = New-Object System.Drawing.RectangleF 0, ($ly + $lh + 18), 1200, 80
$g.DrawString("Stocks $dot IPO $dot Quant Research", $titleFont, $goldBrush, $cardRect, $fmt)
$subRect  = New-Object System.Drawing.RectangleF 0, ($ly + $lh + 92), 1200, 50
$g.DrawString("SEBI Registered Research Analyst  $dot  rootnivesh.in", $subFont, $greyBrush, $subRect, $fmt)

$g.Dispose()
$bmp.Save((Join-Path $imgDir 'og-image.png'), [System.Drawing.Imaging.ImageFormat]::Png)
$bmp.Dispose()
$logo.Dispose()

Write-Host 'Brand assets generated in images/.'
