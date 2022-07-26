<?php

/*
Programmet hanterar dynamisk grafikgenerering.
I detta fall genereras en png-bild som representerar den CAPTCHA-kod som används vid registrering
av nya konton. Denna kod finns för att säkerställa att det är en människa som skapar kontot.
Varje gång programmet körs slumpgenereras en kod bestående av en kombination med bokstäver.
*/

/* 
Startar PHPs inbyggda stöd för sessionshantering genom sessionsvariabler innehållandes information om klienten/sessionen.
Argumenten sätter att HTTP Header för sessionskakan ska ha sant värde på flaggorna för secure och httponly.
Secure ser till att kakan enbart ska användas vid en krypterad anslutning (HTTPS).
Httponly anger att kakan inte ska vara åtkomlig utanför HTTP-protokollet, för att skydda kakan från illvilliga skript hos klienten.
*/
session_start(["cookie_secure" => true, "cookie_httponly" => true]);

// Sätter att responsens typ ska tolkas som 'image/png'.
header("Content-Type: image/png");

// Funktionen skapar en svart 'true color' bild som är 230 pixlar på bredden och 80 pixlar på höjden.
$captchaImg = imagecreatetruecolor(230, 80);

/* 
Skapar och allokerar till bilden en färgkomponent utifrån RGB-värden och returnerar sedan en referens till färgen.
Här är färgen ljusgrå och representerar bakgrundsfärgen. 
*/
$backgroundColor = imagecolorallocate($captchaImg, 250, 250, 250);

// Fyller hela bilden med bakgrundsfärgen.
imagefill($captchaImg, 0, 0, $backgroundColor);

/* 
Skapar och allokerar till bilden en färgkomponent utifrån RGB-värden och returnerar sedan en referens till färgen.
Här är färgen svart och representerar textens färg. 
*/
$textColor = imagecolorallocate($captchaImg, 0, 0, 0);

// Anger filsökvägen till typsnitt-filen.
$font = getcwd() . "\\fonts\\" . "RobotoSlab-Regular.ttf";

$captchaCode = ""; // Lagrar CAPTCHA-koden.

/* 
Loop som utför genereringen av koden och placerar ut varje tecken på slumpmässig plats i bilden,
dock går insättningen från vänster till stegvist till höger för att behålla ordningen på tecknena.
Antalet tecken som ska finnas per kod är satt till 6 st.
*/
for ($i = 0; $i < 6; $i++) {
    // Slumpgenererar ett tal mellan 12 och 32 som representerar typsnittets storlek.
    $fontSize = mt_rand(12, 32);

    // Slumpgenererar ett tal mellan -33 och 33 som representerar typsnittets vinkel för rotation.
    $angle = mt_rand(-33, 33);

    // Slumpgenererar fram ett tecken.
    $char = randomizeChar();

    // Lägger till varje tecken i strängen.
    $captchaCode .= $char;

    // Värdet för tecknets position på x-axel som ökar i varje varv.
    $xPos = 10 + $i * 32;

    // Värdet för tecknets position på y-axel som baseras på aktuellt teckens storlek samt slumpgenererat värde.
    $yPos = 5 + $fontSize + mt_rand(0, 25);

    // Funktion som skriver ut aktuellt true type-tecken på bilden med ovan värden.
    imagettftext($captchaImg, $fontSize, $angle, $xPos, $yPos, $textColor, $font, $char);
}

// Skriver ut programmets skapade bild i PNG-format som svar till webbläsaren.
imagepng($captchaImg);

// Markerar slutet på användning av bilden och förstör den.
imagedestroy($captchaImg);

// Lagrar den kompletta CAPTCHA-koden i sessionsvariabeln.
$_SESSION["captcha"] = $captchaCode;

/* 
Funktionen slumpgenerar ett tecken ur en redan existerande sträng innehållandes stora och små bokstäver.
Bokstäverna är ej hela alfabetet, då tecken som är svårskillda mellan gemen och versal form med detta typsnitt är borttagna.
Slumpar fram ett index för att sedan returnera motsvarande tecken ur denna sträng.
*/
function randomizeChar() {
    $chars = "abdefghijklmnpqrtuyABDEFGHIJKLMNPQRTUY";

    $index = mt_rand(0, 37);
    
    return $chars[$index];
}

?>