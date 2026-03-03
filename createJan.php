<?php

// This PHP program is public domain.
// Created by Tatsuya Fujii.
// https://blue.mints.ne.jp/jan/
// https://github.com/ttyfj/php-jan-barcode
// version 3
// Released Mar 4, 2026

header("Content-Type: image/svg+xml; charset=UTF-8");
header("Content-Security-Policy: script-src 'none'");  // スクリプトの実行を禁止



// urlから、JANコードを取得
$janCode = $_GET['janCode'] ?? '';
$janCode = preg_replace('/\D/', '', $janCode);  // 数字以外は無視


// urlから線幅(1モジュールの幅)を取得
$lineWidth = $_GET['lineWidth'] ?? '';
$lineWidth = filter_var($lineWidth, FILTER_VALIDATE_FLOAT);

if ($lineWidth === false || $lineWidth <= 0) {
    $lineWidth = 1;
}



// urlから線の長さ(高さ)を取得
$lineLength = $_GET['lineLength'] ?? '';
$lineLength = filter_var($lineLength, FILTER_VALIDATE_FLOAT);

if ($lineLength === false || $lineLength <= 0) {
    $lineLength = 32;
}



// urlから線の色を取得。未設定の場合は黒色にする
$lineTempColor = $_GET['lineColor'] ?? 'black';

// バリデーション：記号と英数字以外が含まれていたら拒否
if (preg_match('/[^-a-zA-Z0-9#(), ]/', $lineTempColor)) {
    $lineColor = 'black'; // 不正な文字があればデフォルトへ
} else {
    $lineColor = $lineTempColor;
}

// urlから背景色を取得。未設定の場合は白色にする
$backgroundTempColor = $_GET['backgroundColor'] ?? 'white';

// バリデーション：記号と英数字以外が含まれていたら拒否
if (preg_match('/[^-a-zA-Z0-9#(), ]/', $backgroundTempColor)) {
    $backgroundColor = 'white'; // 不正な文字があればデフォルトへ
} else {
    $backgroundColor = $backgroundTempColor;
}


// 入力されたJANコードの桁数を検証。JAN-8(7または8桁)とJAN-13(12または13桁)の振り分け
$janKind = null;
$alertText = '';

if (preg_match('/^\d{7,8}$/', $janCode)) {
    $janKind = 'JAN-8';
} elseif (preg_match('/^\d{12,13}$/', $janCode)) {
    $janKind = 'JAN-13';
} else {
    $alertText = 'Invalid JAN code!';
    $janKind = 'JAN-13';
    $janCode = '000000000000';
}



// JANの各桁を配列に格納する
$digits = [];

for ($i = 0; $i < strlen($janCode); $i++) {
    $digits[$i] = (int) $janCode[$i];
}





// 各JANの規格上の桁数を変数にする
// チェックディジット計算時の重み(weight)も変数にする
$specificationalJanLength = null;

if ($janKind === 'JAN-13') {
    $specificationalJanLength = 13;
}

if ($janKind === 'JAN-8') {
    $specificationalJanLength = 8;
}




// チェックディジットを、1〜12桁目(JAN-13)、または1〜7桁目(JAN-8)で求める
$sum = 0;

// $digitsを逆順にしてから計算
$reverseDigits = array_reverse(array_slice($digits, 0, $specificationalJanLength - 1));
foreach ($reverseDigits as $i => $val) {
    $sum += ($i % 2 === 0) ? $val * 3 : $val * 1;
}
$checkDigit = (10 - ($sum % 10)) % 10;


// 入力されたJANコードにチェックディジットが無い場合は追加する
if (count($digits) === ($specificationalJanLength - 1)) {
    $digits[$specificationalJanLength - 1] = $checkDigit;
    $janCode .= (string)$checkDigit;
}


// 入力されたJANコードにチェックディジットが有る場合は、チェックディジットが正しいか検証する
if (count($digits) === $specificationalJanLength) {
    if ($digits[$specificationalJanLength - 1] !== $checkDigit) {
        $alertText = 'Invalid check digit!';
    }
}



// エラーの場合は、線の色と背景色を白色にする
if ($alertText !== ''){
    $lineWidth = 2;
    $lineLength = 64;
    $lineColor = 'white';
    $backgroundColor = 'white';
}





// JANの各数字の4分割比率
$ratio[0] = [3,2,1,1];
$ratio[1] = [2,2,2,1];
$ratio[2] = [2,1,2,2];
$ratio[3] = [1,4,1,1];
$ratio[4] = [1,1,3,2];
$ratio[5] = [1,2,3,1];
$ratio[6] = [1,1,1,4];
$ratio[7] = [1,3,1,2];
$ratio[8] = [1,2,1,3];
$ratio[9] = [3,1,1,2];

// 4分割比率の逆順も定義する
for ($j = 0; $j < 10; $j++) {
    $reverseRatio[$j] = [$ratio[$j][3],$ratio[$j][2],$ratio[$j][1],$ratio[$j][0]];
}




// JANの左1桁めの数字で決定される、左データ6文字分(左2桁目から左7桁目まで)のパリティの偶奇
// 0が偶数、1が奇数
$oddEven[0] = [1,1,1,1,1,1];
$oddEven[1] = [1,1,0,1,0,0];
$oddEven[2] = [1,1,0,0,1,0];
$oddEven[3] = [1,1,0,0,0,1];
$oddEven[4] = [1,0,1,1,0,0];
$oddEven[5] = [1,0,0,1,1,0];
$oddEven[6] = [1,0,0,0,1,1];
$oddEven[7] = [1,0,1,0,1,0];
$oddEven[8] = [1,0,1,0,0,1];
$oddEven[9] = [1,0,0,1,0,1];











// 関数定義


// htmlspecialchars()を短縮してh()にする
function h($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');  // ENT_QUOTESは、シングルクォートもエスケープする
}


// 左右のガードバーを描画（SVG出力）
function createGuardBar($xPosition, $lineWidth, $lineLength, $lineColor)
{
    $barHeight = $lineLength + 5 * $lineWidth;

    // 1本目
    echo '<rect fill="' . h($lineColor) . '" x="' . $xPosition . '" y="0" width="' . $lineWidth . '" height="' . $barHeight . '"/>' . "\n";
    
    $xPosition = $xPosition + (2 * $lineWidth);
    
    // 2本目
    echo '<rect fill="' . h($lineColor) . '" x="' . $xPosition . '" y="0" width="' . $lineWidth . '" height="' . $barHeight . '"/>' . "\n";
}






// JANコードの各数字のバーを描画（SVG出力）
function createBarcodeSegment(
    $digitNumber,
    $digits,
    $janKind,
    $ratio,
    $reverseRatio,
    $oddEven,
    $lineWidth,
    $lineLength,
    $xPosition,
    $lineColor
) {
    $leftDataCharacter  = false;
    $rightDataCharacter = false;

    // 左データキャラクタ判定
    if ($janKind === 'JAN-13' && $digitNumber >= 1 && $digitNumber <= 6) {
        $leftDataCharacter = true;
    }
    if ($janKind === 'JAN-8' && $digitNumber >= 0 && $digitNumber <= 3) {
        $leftDataCharacter = true;
    }

    // 右データキャラクタ判定
    if ($janKind === 'JAN-13' && $digitNumber >= 7 && $digitNumber <= 12) {
        $rightDataCharacter = true;
    }
    if ($janKind === 'JAN-8' && $digitNumber >= 4 && $digitNumber <= 7) {
        $rightDataCharacter = true;
    }

    // 左側データキャラクタ
    if ($leftDataCharacter) {

        // JAN-13 のみパリティ判定あり
        if ($janKind === 'JAN-13') {
            $oddOrEven = $oddEven[$digits[0]][$digitNumber - 1];

            if ($oddOrEven == 0) {
                $ratioOfThisSegment = $reverseRatio[$digits[$digitNumber]];
            } else {
                $ratioOfThisSegment = $ratio[$digits[$digitNumber]];
            }
        }

        // JAN-8
        if ($janKind === 'JAN-8') {
            $ratioOfThisSegment = $ratio[$digits[$digitNumber]];
        }

        $xPosition += $ratioOfThisSegment[0] * $lineWidth;

        // バー1
        echo '<rect fill="' . h($lineColor) . '" x="' . $xPosition . '" y="0" width="' .
             ($ratioOfThisSegment[1] * $lineWidth) . '" height="' . $lineLength . '"/>' . "\n";

        $xPosition += $ratioOfThisSegment[1] * $lineWidth;
        $xPosition += $ratioOfThisSegment[2] * $lineWidth;

        // バー2
        echo '<rect fill="' . h($lineColor) . '" x="' . $xPosition . '" y="0" width="' .
             ($ratioOfThisSegment[3] * $lineWidth) . '" height="' . $lineLength . '"/>' . "\n";
    }

    // 右側データキャラクタ
    if ($rightDataCharacter) {

        $ratioOfThisSegment = $ratio[$digits[$digitNumber]];

        // バー1
        echo '<rect fill="' . h($lineColor) . '" x="' . $xPosition . '" y="0" width="' .
             ($ratioOfThisSegment[0] * $lineWidth) . '" height="' . $lineLength . '"/>' . "\n";

        $xPosition += $ratioOfThisSegment[0] * $lineWidth;
        $xPosition += $ratioOfThisSegment[1] * $lineWidth;

        // バー2
        echo '<rect fill="' . h($lineColor) . '" x="' . $xPosition . '" y="0" width="' .
             ($ratioOfThisSegment[2] * $lineWidth) . '" height="' . $lineLength . '"/>' . "\n";
    }
}





// センターバーを描画（SVG出力）
function createCenterBar(
    $xPosition,
    $lineWidth,
    $lineLength,
    $lineColor
) {
    // 最初の1モジュール分をスキップ
    $xPosition += $lineWidth;

    // 1本目
    echo '<rect fill="' . h($lineColor) . '" x="' . $xPosition . '" y="0" width="' .
         $lineWidth . '" height="' . ($lineLength + 5 * $lineWidth) . '"/>' . "\n";

    // 2モジュール分をスキップ
    $xPosition += 2 * $lineWidth;

    // バー2本目
    echo '<rect fill="' . h($lineColor) . '" x="' . $xPosition . '" y="0" width="' .
         $lineWidth . '" height="' . ($lineLength + 5 * $lineWidth) . '"/>' . "\n";
}








// ここからSVGの始まり
if ($janKind === "JAN-13"){$modules = 113;}
if ($janKind === "JAN-8"){$modules = 81;}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<svg xmlns="http://www.w3.org/2000/svg" width="'.($modules*$lineWidth).'" height="'.($lineLength+11*$lineWidth).'" viewBox="0 0 '.($modules*$lineWidth).' '.($lineLength+11*$lineWidth).'">';


//背景を作る
echo '<rect fill="'.h($backgroundColor).'" x="0" y="0" width="'.($modules*$lineWidth).'" height="'.($lineLength+11*$lineWidth).'"/>';



echo '<g>'."\n";  // バーのグループ化の始まり


// x座標
$xPosition = 0;


// 左のマージン
if ($janKind === "JAN-13"){$xPosition = 11 * $lineWidth;}
if ($janKind === "JAN-8"){$xPosition = 7 * $lineWidth;}



// 左のガードバーを作る
createGuardBar($xPosition, $lineWidth, $lineLength, $lineColor);
$xPosition += 3 * $lineWidth;


// 左側データセグメントの範囲決定
// JAN-13 : 2〜7桁目（index 1〜6）
// JAN-8  : 1〜4桁目（index 0〜3）
if ($janKind === 'JAN-13') {
    $startDigit = 1;
    $endDigit   = 6;
} else { // JAN-8
    $startDigit = 0;
    $endDigit   = 3;
}


// 左側セグメントを作る
for ($digitNumber = $startDigit; $digitNumber <= $endDigit; $digitNumber++) {
    createBarcodeSegment(
        $digitNumber,
        $digits,
        $janKind,
        $ratio,
        $reverseRatio,
        $oddEven,
        $lineWidth,
        $lineLength,
        $xPosition,
        $lineColor
    );

    $xPosition += 7 * $lineWidth;
}


// センターバーを作る
createCenterBar($xPosition, $lineWidth, $lineLength, $lineColor);
$xPosition += 5 * $lineWidth;


// 右側データセグメントの範囲決定
// JAN-13 : 8〜13桁目（index 7〜12）
// JAN-8  : 5〜8桁目（index 4〜7）
if ($janKind === 'JAN-13') {
    $startDigit2 = 7;
    $endDigit2   = 12;
} else { // JAN-8
    $startDigit2 = 4;
    $endDigit2   = 7;
}


// 右側セグメントを作る
for ($digitNumber2 = $startDigit2; $digitNumber2 <= $endDigit2; $digitNumber2++) {
    createBarcodeSegment(
        $digitNumber2,
        $digits,
        $janKind,
        $ratio,
        $reverseRatio,
        $oddEven,
        $lineWidth,
        $lineLength,
        $xPosition,
        $lineColor
    );

    $xPosition += 7 * $lineWidth;
}


// 右ガードバーを作る
createGuardBar($xPosition, $lineWidth, $lineLength, $lineColor);



echo '</g>'."\n";  // バーのグループ化の終わり







// ここよりJANの数字の表示


// 各数字の文字をアウトライン(SVGのパス)にして、配列に格納する。フォントはTuffy Regular 21ptでアウトラインをとった。Tuffyフォントはパブリックドメイン。
// それぞれのパスは「幅14px、高さ20px」のスペースに描いています。
$d[0]='M7.14,2.13c2.35,0,3.89,2.1,4.49,4.94.19.91.3,1.9.3,2.91,0,4.18-1.76,7.75-4.88,7.75-2.38,0-3.96-2.09-4.56-4.95-.18-.87-.29-1.83-.29-2.8,0-4.2,1.84-7.85,4.94-7.85ZM4.09,12.6c.43,2,1.37,3.68,2.96,3.68,2.3,0,3.26-3.45,3.26-6.3,0-.91-.09-1.89-.29-2.79-.41-1.97-1.32-3.62-2.88-3.62-2.28,0-3.32,3.53-3.32,6.41,0,.85.08,1.76.27,2.61Z';
$d[1]='M5.52,5.07l2.29-2.56h.94v14.93h-1.61V5.07h-1.62Z';
$d[2]='M2.44,5.49c.52-1.88,2.5-3.38,4.57-3.38s4.31,1.38,4.31,4.53c0,2.88-3.08,4.54-4.07,5.25-1.06.76-3.07,2.74-3.07,4.1h7.41v1.45H2.29c0-3.34,2.31-5.41,4.47-6.93,1.1-.77,2.98-1.78,2.98-3.87s-1.37-3.09-2.74-3.09-2.62.84-3.15,2.5l-1.43-.56Z';
$d[3]='M3.98,14.37c.52,1.14,1.74,1.91,2.86,1.91,1.83,0,3.4-1.19,3.4-3.19s-1.73-3.15-3.36-3.15h-.8v-1.28h1c1.28,0,2.46-1.1,2.46-2.67,0-1.4-1.22-2.48-2.48-2.48h-.06c-1.13,0-1.97.66-2.39,1.62l-1.4-.66c.71-1.4,2.13-2.39,3.86-2.39,2.06,0,4.1,1.58,4.1,3.91,0,1.8-1.18,2.94-1.88,3.29.98.3,2.56,1.76,2.56,3.8,0,2.71-2.2,4.65-5.01,4.65-1.6,0-3.66-1.04-4.45-2.88l1.59-.48Z';
$d[4]='M10.04,11.93h1.94v1.44h-1.94v4.07h-1.61v-4.07H2.08L8.29,2.51h1.74v9.42ZM4.5,11.91h3.97v-6.99l-3.97,6.99Z';
$d[5]='M4.7,10.15l-1.49-.77.96-6.87h6.89v1.45h-5.55l-.68,4.39c.59-.36,1.37-.56,2.52-.56,2.23,0,3.95,1.44,4.43,3.67.08.4.13.83.13,1.28,0,2.65-1.76,5-4.62,5-2.37,0-4.13-1.52-4.75-3.68l1.6-.29c.36,1.05,1.13,2.51,3.15,2.51,1.8,0,3.02-1.45,3.02-3.57,0-.3-.03-.58-.09-.85-.33-1.57-1.55-2.7-2.87-2.7-1.64,0-2.35.74-2.67.98Z';
$d[6]='M7.05,8.64c2.21,0,4.11,1.53,4.56,3.65.07.31.1.64.1.96,0,2.12-1.83,4.48-4.69,4.48-2.36,0-4.03-1.7-4.44-3.61-.06-.29-.09-.59-.09-.89,0-2.09,1.24-3.83,1.72-4.72l3.42-5.99h1.59l-3.59,6.43c.46-.19.8-.32,1.4-.32ZM10.12,12.61c-.29-1.36-1.48-2.44-3.07-2.44-1.83,0-3.01,1.52-3.01,3.05,0,.23.02.44.06.65.3,1.4,1.55,2.34,2.97,2.34,1.9,0,3.12-1.49,3.12-2.94,0-.22-.03-.43-.07-.65Z';
$d[7]='M6.55,17.44h-1.87l5.29-13.4H2.31v-1.53h9.93l-5.69,14.93Z';
$d[8]='M11.3,5.12c.06.25.08.51.08.79,0,1.99-1.28,2.87-1.93,3.25.91.42,2.28,1.37,2.62,3.02.05.26.08.52.08.82,0,2.71-2.33,4.73-5.01,4.73-2.35,0-4.33-1.57-4.8-3.74-.06-.32-.1-.65-.1-.98,0-2.27,1.64-3.45,2.62-3.85-.65-.38-1.54-1.03-1.81-2.36-.06-.27-.09-.56-.09-.89,0-2.16,1.78-3.82,4.2-3.82,2.11,0,3.75,1.25,4.13,3.04ZM10.54,13.01c0-.24-.02-.46-.06-.68-.33-1.54-1.76-2.47-3.29-2.47-1.85,0-3.32,1.15-3.32,3.15,0,.24.03.47.07.7.33,1.52,1.67,2.57,3.21,2.57,1.87,0,3.39-1.47,3.39-3.27ZM4.64,6.52c.25,1.19,1.25,1.89,2.54,1.89,1.47,0,2.58-.9,2.58-2.49,0-.19-.02-.38-.06-.55-.23-1.07-1.12-1.83-2.54-1.83-1.68,0-2.58,1.08-2.58,2.38,0,.22.02.41.06.6Z';
$d[9]='M7.31,11.26c-2.21,0-4.11-1.53-4.56-3.65-.06-.31-.1-.64-.1-.97,0-2.12,1.84-4.49,4.69-4.49,2.37,0,4.04,1.72,4.44,3.62.06.3.09.59.09.89,0,2.1-1.23,3.85-1.72,4.73l-3.32,6.05h-1.72l3.62-6.49c-.46.19-.8.32-1.4.32ZM4.25,7.3c.29,1.36,1.48,2.43,3.06,2.43,1.83,0,3.02-1.51,3.02-3.05,0-.22-.02-.43-.07-.64-.3-1.4-1.55-2.36-2.97-2.36-1.89,0-3.11,1.51-3.11,2.95,0,.23.02.44.07.66Z';




// JANの数字をSVGで表示する
echo '<g>'."\n";

// JAN-13の場合
if ($janKind === "JAN-13") {
    echo '<path fill="'.h($lineColor).'" d="'.$d[$digits[0]]. '" transform="translate('.(2     )*$lineWidth.','.($lineLength+1*$lineWidth).') scale('.($lineWidth/2).','.($lineWidth/2).')" />'."\n";
    echo '<path fill="'.h($lineColor).'" d="'.$d[$digits[1]]. '" transform="translate('.(7*2   )*$lineWidth.','.($lineLength+1*$lineWidth).') scale('.($lineWidth/2).','.($lineWidth/2).')" />'."\n";
    echo '<path fill="'.h($lineColor).'" d="'.$d[$digits[2]]. '" transform="translate('.(7*3   )*$lineWidth.','.($lineLength+1*$lineWidth).') scale('.($lineWidth/2).','.($lineWidth/2).')" />'."\n";
    echo '<path fill="'.h($lineColor).'" d="'.$d[$digits[3]]. '" transform="translate('.(7*4   )*$lineWidth.','.($lineLength+1*$lineWidth).') scale('.($lineWidth/2).','.($lineWidth/2).')" />'."\n";
    echo '<path fill="'.h($lineColor).'" d="'.$d[$digits[4]]. '" transform="translate('.(7*5   )*$lineWidth.','.($lineLength+1*$lineWidth).') scale('.($lineWidth/2).','.($lineWidth/2).')" />'."\n";
    echo '<path fill="'.h($lineColor).'" d="'.$d[$digits[5]]. '" transform="translate('.(7*6   )*$lineWidth.','.($lineLength+1*$lineWidth).') scale('.($lineWidth/2).','.($lineWidth/2).')" />'."\n";
    echo '<path fill="'.h($lineColor).'" d="'.$d[$digits[6]]. '" transform="translate('.(7*7   )*$lineWidth.','.($lineLength+1*$lineWidth).') scale('.($lineWidth/2).','.($lineWidth/2).')" />'."\n";
    echo '<path fill="'.h($lineColor).'" d="'.$d[$digits[7]]. '" transform="translate('.(7*8 +5)*$lineWidth.','.($lineLength+1*$lineWidth).') scale('.($lineWidth/2).','.($lineWidth/2).')" />'."\n";
    echo '<path fill="'.h($lineColor).'" d="'.$d[$digits[8]]. '" transform="translate('.(7*9 +5)*$lineWidth.','.($lineLength+1*$lineWidth).') scale('.($lineWidth/2).','.($lineWidth/2).')" />'."\n";
    echo '<path fill="'.h($lineColor).'" d="'.$d[$digits[9]]. '" transform="translate('.(7*10+5)*$lineWidth.','.($lineLength+1*$lineWidth).') scale('.($lineWidth/2).','.($lineWidth/2).')" />'."\n";
    echo '<path fill="'.h($lineColor).'" d="'.$d[$digits[10]].'" transform="translate('.(7*11+5)*$lineWidth.','.($lineLength+1*$lineWidth).') scale('.($lineWidth/2).','.($lineWidth/2).')" />'."\n";
    echo '<path fill="'.h($lineColor).'" d="'.$d[$digits[11]].'" transform="translate('.(7*12+5)*$lineWidth.','.($lineLength+1*$lineWidth).') scale('.($lineWidth/2).','.($lineWidth/2).')" />'."\n";
    echo '<path fill="'.h($lineColor).'" d="'.$d[$digits[12]].'" transform="translate('.(7*13+5)*$lineWidth.','.($lineLength+1*$lineWidth).') scale('.($lineWidth/2).','.($lineWidth/2).')" />'."\n";
}

// JAN-8の場合
if ($janKind === "JAN-8") {
    echo '<path fill="'.h($lineColor).'" d="'.$d[$digits[0]]. '" transform="translate('.(3+7*1  )*$lineWidth.','.($lineLength+1*$lineWidth).') scale('.($lineWidth/2).','.($lineWidth/2).')" />'."\n";
    echo '<path fill="'.h($lineColor).'" d="'.$d[$digits[1]]. '" transform="translate('.(3+7*2  )*$lineWidth.','.($lineLength+1*$lineWidth).') scale('.($lineWidth/2).','.($lineWidth/2).')" />'."\n";
    echo '<path fill="'.h($lineColor).'" d="'.$d[$digits[2]]. '" transform="translate('.(3+7*3  )*$lineWidth.','.($lineLength+1*$lineWidth).') scale('.($lineWidth/2).','.($lineWidth/2).')" />'."\n";
    echo '<path fill="'.h($lineColor).'" d="'.$d[$digits[3]]. '" transform="translate('.(3+7*4  )*$lineWidth.','.($lineLength+1*$lineWidth).') scale('.($lineWidth/2).','.($lineWidth/2).')" />'."\n";
    echo '<path fill="'.h($lineColor).'" d="'.$d[$digits[4]]. '" transform="translate('.(3+7*5+5)*$lineWidth.','.($lineLength+1*$lineWidth).') scale('.($lineWidth/2).','.($lineWidth/2).')" />'."\n";
    echo '<path fill="'.h($lineColor).'" d="'.$d[$digits[5]]. '" transform="translate('.(3+7*6+5)*$lineWidth.','.($lineLength+1*$lineWidth).') scale('.($lineWidth/2).','.($lineWidth/2).')" />'."\n";
    echo '<path fill="'.h($lineColor).'" d="'.$d[$digits[6]]. '" transform="translate('.(3+7*7+5)*$lineWidth.','.($lineLength+1*$lineWidth).') scale('.($lineWidth/2).','.($lineWidth/2).')" />'."\n";
    echo '<path fill="'.h($lineColor).'" d="'.$d[$digits[7]]. '" transform="translate('.(3+7*8+5)*$lineWidth.','.($lineLength+1*$lineWidth).') scale('.($lineWidth/2).','.($lineWidth/2).')" />'."\n";
}

echo '</g>'."\n";





// エラー時にメッセージを表示する
if ($alertText !== '') {
    echo '<text x="12" y="16" fill="red" font-family="sans-serif" font-size="12">'.h($alertText).'</text>';
}



echo '</svg>';