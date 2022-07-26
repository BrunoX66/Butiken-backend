<?php

/* 
Programmet hanterar ett kontaktformulär för kunder till nätbutiken som vill nå
nätbutikens kundtjänst med exempelvis frågor eller förslag.
För extra säkerhet kontrollerar programmet att alla obligatoriska fält har fyllts i istället för att lämna ansvaret 
åt HTML-dokumentet på klientsidan - detta gör att programmet kan visa ett felmeddelande till klienten om den ser ett tomt värde.
Kontakten sker genom att ett meddelande epostas till nätbutikens epostadress, inkl.
eventuella bifogade filer.
Ämnesraden är redan bestämd genom tre alternativ användaren fått välja mellan i formuläret.
Programmet utnyttjar en klass/modul som heter 'PHPMailer' för att möjliggöra hantering och sändning av epost.
*/

/* 
PHPMailer-klassen använder en specifik namnrymd.
Detta importerar klassen till det egna programmets namnrymd.
*/
use PHPMailer\PHPMailer\PHPMailer;

/* 
Dessa rader inkluderar PHPMailers specifika klassfiler.
Detta anger vilka filer som ska laddas in i programmet.
*/
require "PHPMailer/PHPMailer.php"; // Huvudfilen för PHPMailer
require "PHPMailer/SMTP.php"; // För SMTP
require "PHPMailer/Exception.php"; // För undantag genererad av PHPMailer

// Läser av innehållet i HTML-filen och lagrar det som string i variabeln.
$html = file_get_contents("contact.html");

// Deklarering av tomma variabler som ska representera olika status- och felmeddelanden.
$submitStatus = $emailError = $subjectError = $msgError = "";

/* 
Kontrollerar om anropet till programmet skett via HTTP metoden POST samt om nyckeln 'submit' som skickas med i POST,
är deklarerad och innehåller ett värde som inte är NULL.
Här kontrolleras det för att se om klienten klickat på knappen 'Submit' och bekräfta att sändningen gjorts via POST.
*/
if (isset($_POST["submit"]) && $_SERVER["REQUEST_METHOD"] == "POST") {

    $error = ""; // Variabel som ska fånga upp eventuella felmeddeladen.

    /* 
    Kontroll för att se om variabeln 'name' som skickats via POST är tom.
    Är den tom markeras namnet som ej tillgänglig.
    I annat fall så för säkerhetsskull saneras värdet innan det sparas.
    */
    if (empty($_POST["name"])) {
        $name = "N/A";
    } else {
        $name = inputSanitizer($_POST["name"]);
    }

    /* 
    Kontroll för att se om variabeln 'email' som skickats via POST är tom.
    Är den tom sätts variablerna för fel med ett felmeddelande som beskriver felet för klienten.
    I annat fall så för säkerhetsskull saneras värdet innan det sparas.
    */
    if (empty($_POST["email"])) {
        $error = $emailError = "Email is required.";
    } else {
        $email = inputSanitizer($_POST["email"]);
    }

    /* 
    Kontroll för att se om variabeln 'subject' som skickats via POST är tom.
    Är den tom sätts variablerna för fel med ett felmeddelande som beskriver felet för klienten.
    I annat fall så för säkerhetsskull saneras värdet innan det sparas.
    */
    if (empty($_POST["subject"])) {
        $error = $subjectError = "Subject is required.";
    } else {
        $subject = inputSanitizer($_POST["subject"]);
    }

    /* 
    Kontroll för att se om variabeln 'message' som skickats via POST är tom.
    Är den tom sätts variablerna för fel med ett felmeddelande som beskriver felet för klienten.
    I annat fall så för säkerhetsskull saneras värdet innan det sparas.
    */
    if (empty($_POST["message"])) {
        $error = $msgError = "Message is required.";
    } else {
        $message = inputSanitizer($_POST["message"]);
    }

    /* 
    Om inga felmeddelanden uppstått så utförs sändningen av kundens meddelande.
    Först kontrolleras att variabeln $_FILES fått sig värdet för 'file' tilldelat (ej är tom) och inget fel uppstått vid filuppladdningen.
    Syftet med denna kontroll är att se om kunden bifogat en fil till sitt meddelande.
    Finns det en fil så skickas filsökvägen samt filnamnet med till funktionen som hanterar sändning av meddelandet.
    I annat fall skickas endast textmeddelandet.
    Namn, epostadress och ämne värdena inkluderas också i sändning av meddelandet.
    */
    if (!$error) {
        if (isset($_FILES["file"]) && $_FILES["file"]["error"] === 0) {
            $filepath = $_FILES["file"]["tmp_name"];
            $filename = $_FILES["file"]["name"]; 
        
            sendMsg($name, $email, $subject, $message, $filepath, $filename);
        } else {
            sendMsg($name, $email, $subject, $message);
        }
    }
}

setFieldValues(); // Sätter de värden som ska stå i fälten i formuläret.

setFormError(); // Sätter eventuella felmeddelanden till klienten.

setSubmitStatus($submitStatus); // Sätter statusmeddelandet till klienten.

echo($html); // Skriver ut HTML-strängen som respons till klientens webbläsare.

/* 
Funktionen sätter de värden som ska stå i formulärets fält.
Syftet är att om något fel sker i inmatningen ska de inmatade textvärdena och alternativen bevaras vid
omladdning av webbsidan för att användaren ska slippa skriva om allt varje gång.
Detta görs genom kontroll att de variabler som skickats via POST finns och ej är null, då sparas deras värden i variabler.
Annars så sätts variablerna till tomma strängar.
Avslutningsvis ersätts markörerna för fälten i HTML-dokumentet med värdena i de sparade strängvariablerna,
alltså de tidigare inmatade värdena eller tomma strängar.
*/
function setFieldValues() {
    global $html;
    
    if (isset($_POST["name"])) {
        $nameField = $_POST["name"];
    } else {
        $nameField = "";
    }

    if (isset($_POST["email"])) {
        $emailField = $_POST["email"];
    } else {
        $emailField = "";
    }

    if (isset($_POST["subject"])) {
        $subjectField = $_POST["subject"];
    } else {
        $subjectField = "";
    }

    if (isset($_POST["message"])) {
        $messageField = $_POST["message"];
    } else {
        $messageField = "";
    }
    
    $html = str_replace("---name_value---", $nameField, $html);
    $html = str_replace("---email_value---", $emailField, $html);

    /* 
    Här görs en speciell kontroll vid selektering av ämne eftersom användaren valt ur förbestämda värden i en dropdown-meny.
    Om ämnesvärdet inte är tom så flyttas 'selected' till den plats i HTML-strängen som motsvarar aktuellt ämnesvärde.
    */
    if ($subjectField !== "") {
        $html = str_replace("selected", "", $html);
        $html = str_replace("$subjectField\"", "$subjectField\" selected", $html);
    }
    
    $html = str_replace("---message_value---", $messageField, $html);
}

// Funktionen nollställer variabeln som innehåller värdena som skickats via HTTP POST genom att skapa en ny tom array.
function clearFieldValues() {
    $_POST = array();
}

/* 
Funktion som sätter eventuella felmeddelanden och visar de för användaren.
Funktionen får åtkomst till globalt deklarerade variabler.
I detta fall de variabler som innehåller HTML-strängen och felmeddelanden.
Dessa meddelanden appliceras i HTML-dokumentet genom att ersätta markörer.
Här felmeddelanden för: epost, ämne, meddelande
*/
function setFormError() {
    global $html;
    global $emailError;
    global $subjectError;
    global $msgError;


    $html = str_replace("---email_error---", $emailError, $html);
    $html = str_replace("---subject_error---", $subjectError, $html);
    $html = str_replace("---message_error---", $msgError, $html);
}

/* 
Funktion som sätter ett eventuellt statusmeddelande och visar den för användaren.
Funktionen får åtkomst till en globalt deklarerad variabel för HTML-strängen.
Detta meddelande appliceras i HTML-dokumentet genom att ersätta markören med argumentvärdet.
*/
function setSubmitStatus($msg) {
    global $html;

    $html = str_replace("---submit_status---", $msg, $html);
}

/* 
Funktion som av säkerhetsskäl sanerar inmatningsvärden, i de fall en sträng illvilligt försöker utnyttja sårbarheter i programmet.
Detta görs genom att ta bort eventuell PHP samt HTML-kod ur strängvärdet från argumentet samt borttagning av
eventuella omvända snedstreck som använts. Även blanksteg i början och slutet av strängen tas bort.
Returnerar den sanerade strängen.
*/
function inputSanitizer(string $string) {
    $string = trim($string);
    $string = stripslashes($string);
    $string = strip_tags($string);

    return $string;
} 

/* 
Funktionen som hanterar sändning av meddelandet från kunden.
Här används modulen 'PHPMailer' för att skapa och sända epost.
Sändningen använder nätbutikens egna epostadress för att sända till sig själv,
dock med namn på avsändaren ändrat till att uppmärksamma att det är sänt från en kund.
Till alla meddelanden appliceras i början av meddelandet en text som uppmärksammar
att det är sänt från en kund, kundens eventuella namn och kundens epostadress.
Eventuell bifogad fil skickas också med.
Avslutningsvis uppdateras statusmeddelandet för att meddela kunden att meddelandet skickats.
Även ett anrop till funktion som nollställer variabeln som innehåller värdena som skickats via HTTP POST görs för
att inte formulärets fältvärden ska skrivas ut igen efter sändningen.
De två sista parametrarna i funktionen har standardvärden som beskriver att man inte behöver ange dessa värden vid
anrop till funktionen, detta betyder då att ingen bifogad fil har laddats upp till webbservern.
*/
function sendMsg($name, $email, $subject, $message, $file = false, $filename = "") {
    $mail = new PHPMailer(); // Skapar en instans av PHPMailer.
    $mail -> CharSet = "UTF-8"; // Anger att teckenkodning ska vara UTF-8.
    $mail -> isSMTP(); // Anger att SMTP ska användas.
    $mail -> Host = "smtp.gmail.com"; // Anger Googles mailserver.
    $mail -> Port = 587; // Anger SMPTP-porten för TLS autentisering.
    $mail -> SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Anger krypteringsmetod för SMTP-anslutningen.
    $mail -> SMTPAuth = true; // Anger att autentisering ska ske med SMTP.
    $mail -> Username = "username"; // Anger användarnamn/e-postadress som ska skickas till mailservern.
    $mail -> Password = "password"; // Anger lösenordet som ska skickas till mailservern.

    /* 
    Anger epostadress och namn på den som ska stå som avsändare.
    Samma epostadress som i användarnamnet.
    */
    $mail -> setFrom($mail -> Username, "Message from customer");

    /* 
    Anger vem som ska stå som mottagare.
    Samma epostadress som i användarnamnet.
    */
    $mail -> addAddress($mail -> Username);

    $mail -> Subject = $subject; // Anger vad som ska stå på ämnesraden.

    
    // Anger innehållet i meddelandet, epostens kropp.
    $mail -> Body = "New message from a customer.\nName: $name\nEmail: $email\n\nMessage:\n\n"
     . $message;

    /* 
    Kontrollerar om en fil skickats med som argument till funktionen.
    Om det finns bifogas filen till epostmeddelandet och den temporärt lagrade filen
    döps om vid bifogandet till det ursprungliga filnamnet.
    */
    if ($file) {
        $mail -> addAttachment($file, $filename);
    }

    $mail -> send();

    global $submitStatus; // Åtkomst till global variabel.
    $submitStatus = "The message has been submitted! We'll be in touch shortly.";
    clearFieldValues();
}

?>