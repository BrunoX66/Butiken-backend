<?php

/* 
Programmet hanterar kundvagnen i denna nätbutik.
Kundvagnssidan kräver olika hanteringar i olika situationer - om användaren är inloggad eller ej.
Hämtning av kundvagnens produkter sker därför antingen via en kontroll i databasen eller hos aktuell session.
Kundvagnen ska även kunna visa detaljer som valt antal av varje produkt samt total priset för alla varor.
Även möjlighet att ta bort en viss produkt hanteras av programmet.
*/

/* 
Startar PHPs inbyggda stöd för sessionshantering genom sessionsvariabler innehållandes information om klienten/sessionen.
Argumenten sätter att HTTP Header för sessionskakan ska ha sant värde på flaggorna för secure och httponly.
Secure ser till att kakan enbart ska användas vid en krypterad anslutning (HTTPS).
Httponly anger att kakan inte ska vara åtkomlig utanför HTTP-protokollet, för att skydda kakan från illvilliga skript hos klienten.
*/
session_start(["cookie_secure" => true, "cookie_httponly" => true]);

// Läser av innehållet i HTML-filen och lagrar det som string i variabeln.
$html = file_get_contents("cart.html");

/* 
Delar upp HTML-strängen i flera delar, separerad med markörerna som finns i HTML-dokumentet.
Lagrar varje delsträng i en variabel som array.
*/
$htmlPieces = explode("<!--===product===-->", $html);

/* 
Kontrollerar om anropet till programmet skett via HTTP metoden GET samt om nyckeln 'removeId' som skickas med i GET,
är deklarerad och innehåller ett värde som inte är NULL.
Här kontrolleras det för att se om klienten anropat borttagning av en produkt ur kundvagnen
och bekräfta att sändningen gjorts via GET.
*/
if (isset($_GET["removeId"]) && $_SERVER["REQUEST_METHOD"] == "GET") {
    removeCartItem(); // Anropar funktionen som tar bort produkt från kundvagnen.
}

/* 
Kontrollerar om ett specifikt index i aktuella sessionsvariabeln existerar, 
är deklarerad och innehåller ett värde som inte är NULL.
Här kontrolleras det för att se om klienten är inloggad då indexet i variabeln finns endast hos en inloggad användare.
I sådana fall hämtas kundvagnen som är bunden till det aktuella kontot.
*/
if (isset($_SESSION["current_user"])) {
    getAccountCart(); // Hämtar kundvagn för inloggad konto.

/* 
Kontrollerar om ett specifikt index i cookievariabeln existerar, 
är deklarerad och innehåller ett värde som inte är NULL.
Här kontrolleras det för att se om klienten har en kaka som anger ett sessions-id för ett tidigare inloggat konto sparad.
I sådana fall kontrolleras detta sparade sessions-id om det matchar ett existerande sessions-id i databasen,
gör den det så hämtas information om kontot, sedan anropas hämtning av kundvagn för kontot.
Om den inte matchar något konto hämtas kundvagn för sessionen.
*/ 
} elseif (isset($_COOKIE["account_session_id"])) {
    if (getAccountSessInfo()) {
        getAccountCart();
    } else {
        getSessionCart();
    }

// I alla andra fall hämtas kundvagn för sessionen.
} else {
    getSessionCart();
}

/* 
Funktion som hämtar en anslutningsreferens till webbplatsens databas.
Returnerar därefter referensen till anslutningen.
Skulle anslutningen misslyckas avslutas programmet med ett felmeddelande.
*/
function connectToDatabase() {
    $dbserver = "atlas.dsv.su.se";
    $dbusername = "username";
    $dbpassword = "password";
    $dbname = "name";

    $connection = mysqli_connect($dbserver, $dbusername, $dbpassword, $dbname);

    if (!$connection) {
        die("Connection to database failed: " . mysqli_connect_error());
    }

    // Sätts för att frågor mot databasen ska exekveras direkt som standard.
    mysqli_autocommit($connection, true);

    return $connection;
}

/* 
Funktion som hanterar borttagning av produkt från klientens kundvagn.
Det finns två scenarion för kundvagn - antingen en inloggad användare eller en sessionsanvändare.
I första fallet kontrolleras om det är en inloggad användare genom att kontrollera om sessionsvariabeln
har indexet 'current_user' deklarerad som innebär en inloggad användare.
Finns det skickas en förfrågan till databasen om att ta bort den rad där aktuellt användarnamn och produkt-id finns.
För säkerhetsskull sker frågan med en preparerad SQL-fråga.
I andra scenariot för sessionen så tas produkten bort genom borttagning av den del av sessionsvariabeln som innehåller
aktuella produkten. Skulle den kundvagnen inte innehålla fler produkter tas den index som representerar
kundvagnen bort ur sessionsvariabeln för att "nollställa" kundvagnen.
I samtliga fall används produkt-id som skickats via HTTP GET för att funktionen ska veta vilken produkt det gäller.
*/
function removeCartItem() {

    $productId = $_GET["removeId"];

    if (isset($_SESSION["current_user"])) {
        $connection = connectToDatabase();

        $username = $_SESSION["current_user"]["username"];

        $sql = "DELETE FROM CartItems WHERE username=? AND productId=?";

        $stmt = mysqli_prepare($connection, $sql);

        mysqli_stmt_bind_param($stmt, "si", $username, $productId);

        mysqli_stmt_execute($stmt);

        mysqli_stmt_close($stmt);
        mysqli_close($connection);
    } else {
        unset($_SESSION["cart"][$productId]);
        if (empty($_SESSION["cart"])) {
            unset($_SESSION["cart"]);
        }
    }

}

/* 
Funktionen hanterar hämtning av aktuell kontosession om klienten har en lagrad kaka med sessions-id för ett konto.
En kontroll utförs genom en preparerad SQL-fråga till databasen för att hämta den eventuella rad som matchar sessions-id i kakan.
Hämtning görs för epost och användarnamn. När hämtning från databas är gjord kontrolleras om resultatet för epost och användarnamn
innehåller hämtade värden och ej är av falskt värde, i sådant fall lagras dessa två värden i sessionsvariabeln på det index som representerar
inloggad användare. I annat fall om epost och användarnamn är tomma så returneras 'false'.
*/
function getAccountSessInfo() {
    $connection = connectToDatabase();

    $sql = "SELECT email, username FROM Users WHERE sessionId=?";

    $stmt = mysqli_prepare($connection, $sql);

    mysqli_stmt_bind_param($stmt, "s", $_COOKIE["account_session_id"]);

    if (!mysqli_stmt_execute($stmt)) {
        echo("Account session retrieval failed: " . mysqli_error($connection));
    }

    mysqli_stmt_bind_result($stmt, $emailCol, $usernameCol);

    mysqli_stmt_fetch($stmt);

    mysqli_stmt_close($stmt);

    mysqli_close($connection);

    if ($emailCol && $usernameCol) {
        $_SESSION["current_user"]["email"] = $emailCol;
        $_SESSION["current_user"]["username"] = $usernameCol;
        return true;
    } else {
        return false;
    }
}

/* 
Funktion som hanterar hämtning av kundvagn för inloggad användare.
En fråga till databasen skickas för hämtning av alla rader med produkt-id och antal valda produkter som
matchar det aktuella användarnamnet.
Först utförs en kontroll om att frågan returnerade ett resultat innehållandes värden.
Gör den det sätter den kundvagnens titel och i en loop hämtas varje rad som representerar den rad i kundvagnen
med produkten. I varje varv skrivs produktinformationen ut. Efter loopen är klar beräknas och skrivs totalpriset ut.
Om resultatet från databasen är tom markeras kundvagnen som tom.
*/
function getAccountCart() {
    global $htmlPieces;

    $username = $_SESSION["current_user"]["username"];

    $connection = connectToDatabase();

    $sql = "SELECT productId, quantity FROM CartItems WHERE username='$username'";

    $result = mysqli_query($connection, $sql);

    if ($row = mysqli_fetch_assoc($result)) {
        setCartStatus("Your selected items");
        do {
            setCartItem($connection, $htmlPieces[1], $row["productId"], $row["quantity"]);
        } while ($row = mysqli_fetch_assoc($result));
        setTotalPrice(calcTotalPrice());
    } else {
        setEmptyCart();
    }
    
    mysqli_free_result($result);
    mysqli_close($connection);
}

/* 
Funktion som hanterar hämtning av kundvagn för sessionsanvändare (ej inloggad).
Här kontrolleras den del av sessionsvariabeln som representerar kundvagnen i sessionen.
Existerar den så sätts titeln för kundvagnen och en loop går igenom varje produkt i kundvagnen.
I varje varv skrivs produktinformationen ut. Efter loopen är klar beräknas och skrivs totalpriset ut.
Om kundvagnen i sessionsvariabeln är tom markeras kundvagnen som tom.
*/
function getSessionCart() {
    global $htmlPieces;

    $connection = connectToDatabase();

    if (isset($_SESSION["cart"])) {
        setCartStatus("Your selected items");
        foreach ($_SESSION["cart"] as $id => $quantity) {
            setCartItem($connection, $htmlPieces[1], $id, $quantity);
        }
        setTotalPrice(calcTotalPrice());
    } else {
        setEmptyCart();
    }
    
    mysqli_close($connection);
}

/* 
Funktionen hanterar hämtning av produktinformation från databasen.
Den förutsätter att man i argumenten skickar med en anslutningsreferens, produkt-id som man vill ha info om
och namnet på den kolumn i tabellen man vill ha information om.
Hämtning görs med SQL-fråga och resultatet kommer i form av ett objekt.
Returnerar det värde i objektet som representerar kolumnvärdet som efterfrågats.
*/
function getProductInfo($connection, $productId, $colName) {
    $sql = "SELECT $colName FROM Products WHERE productId='$productId'";

    $result = mysqli_query($connection, $sql);

    $obj = mysqli_fetch_object($result);

    mysqli_free_result($result);

    return $obj -> $colName;
}

/* 
Funktion som hanterar när en kundvagn markeras som tom.
Titeln för kundvagnen sätts för att informera att kundvagnen är tom.
Sedan ersätts markörerna i HTML-dokumentet med strängvärden.
Strängvärdena är värden som kundvagnen ska ha när den saknar produkter.
Kundvagnsraden skrivs ut och inget totalpris representeras med ett bindesstreck.
*/
function setEmptyCart() {
    global $htmlPieces;

    setCartStatus("Your cart is empty");

    $htmlPieces[1] = str_replace("---product_name---", "N/A", $htmlPieces[1]);
    $htmlPieces[1] = str_replace("---product_id---", "", $htmlPieces[1]);
    $htmlPieces[1] = str_replace("---product_price---", "-", $htmlPieces[1]);
    $htmlPieces[1] = str_replace("---product_quantity---", "0", $htmlPieces[1]);
    $htmlPieces[1] = str_replace("---remove_item_script---", "", $htmlPieces[1]);
    $htmlPieces[1] = str_replace("---remove_item_text---", "", $htmlPieces[1]);

    echo($htmlPieces[1]);

    setTotalPrice("-");
}

/* 
Funktion som hanterar insättning och utskrift av totalpris.
Totalprisvärdet anges som argument till funktionen.
Detta skrivs ut i HTML-strängen genom att ersätta relevant markör.
*/
function setTotalPrice($totalPrice) {
    global $htmlPieces;
    $htmlPieces[2] = str_replace("---total_price---", $totalPrice, $htmlPieces[2]);
    echo($htmlPieces[2]);
}

/* 
Funktionen hanterar beräkning av totalpriset i kundvagnen.
Detta görs genom deklarera en statisk variabel med ursprungligt värde på 0.
Genom att den är statisk så bevaras det uppdaterade värdet även efter att funktionen exekverats klart.
Via argumenten så skickas pris på aktuell produkt och dess antal.
Dessa två argumentvärden multipliceras och sedan adderas resultatet med och lagras i det statiska värdet
varje gång funktionen anropas. Funktionen returnerar även det aktuella totalpriset.
För att funktionen ska veta om en beräkning behövs eller enbart ska returnera totalpriset så görs en kontroll
där beräkning sker endast om argumenten för pris och antal är större än 0. Därför kan anrop till funktionen göras
utan argumentvärden, då sätts standardvärden för båda variablerna till 0 och returnerar endast totalpriset.
*/
function calcTotalPrice($price = 0, $quantity = 0) {
    static $totalPrice = 0;
    if ($price > 0 && $quantity > 0) {
        return $totalPrice += ($price * $quantity);
    } else {
        return $totalPrice;
    }
}

/* 
Funktionen sätter titeln/statusen för kundvagnen med den text som skickats som argument.
Detta genom att ersätta markören i HTML-dokumentet och sedan skriva ut den.
*/
function setCartStatus($statusTxt) {
    global $htmlPieces;

    $htmlPieces[0] = str_replace("---cart_status---", $statusTxt, $htmlPieces[0]);

    echo($htmlPieces[0]);
}

/* 
Funktionen hanterar utskrift av en kundvagnsrad med produktinformationen.
Med argumentvärdena hämtar den information om aktuell produkt-id, alltså namn och pris.
Sedan skickas pris och antal som argument till funktion för addering till totalpris.
Slutligen sker utskrift med produktinformationen genom ersättning av markörer i HTML-strängen.
*/
function setCartItem($connection, $productHTML, $productId, $quantity) {
    $name = getProductInfo($connection, $productId, "name");
    $price = getProductInfo($connection, $productId, "price");
    
    calcTotalPrice($price, $quantity);

    $productHTML = str_replace("---product_name---", $name, $productHTML);
    $productHTML = str_replace("---product_id---", $productId, $productHTML);
    $productHTML = str_replace("---product_price---", $price, $productHTML);
    $productHTML = str_replace("---product_quantity---", $quantity, $productHTML);
    $productHTML = str_replace("---remove_item_script---", "cart.php?removeId=$productId", $productHTML);
    $productHTML = str_replace("---remove_item_text---", "Remove", $productHTML);

    echo($productHTML);
}

?>