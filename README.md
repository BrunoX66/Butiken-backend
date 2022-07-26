# Butiken-backend
A web development project focusing on back-end, web server processing of client requests and content delivery. Made with HTML and PHP, as well as using PHPMailer library for email functionality.
The PHP scripts communicate with a MySQL database server through SQL statements to store and fetch data.



## Om projektet
Projektet består av en webbplats som representerar en mindre nätbutik.
Den är baserad på ett tidigare frontend-projekt där jag designade en nätbutik med namnet Butiken.
Det här projektet kan betraktas som en omarbetad variant av den tidigare nätbutiken, där webbplatsen nu är uppbyggd utifrån program på webbserversidan istället.
Programmen är skrivna i språket PHP och webbsidorna presenteras i form av HTML-dokument.  

Webbplatsen består av följande webbsidor:  
- Nätbutikens huvudsida
- Kundvagn
- Kontakt
- Inloggning
- Registrering
- Återställning av lösenord  

Även program för följande finns:  
- Utloggning
- Generering av CAPTCHA-bild  

En beskrivning av nätbutiken är att det är ett företag som säljer ett antal produkter på sin webbplats.
Alla produkter och dess information finns lagrade i en databas som hämtas och presenteras för besökaren.
Besökaren har möjlighet att handla på två sätt, antingen i ett temporärt besök som gäller för sessionen, alltså tills besökaren stänger webbläsaren, eller som en inloggad användare på ett registrerat konto.
Den enda skillnaden mellan en inloggad besökare och en sessionsbesökare är att i en session sparas kundvagnen med inlagda produkter tills sessionen avslutas, 
medan hos en registrerad kund sparas kundvagnen per kontobasis – användaren kan logga in på en annan enhet och se samma inlagda produkter i kundvagnen.
Registrerade konton och deras kundvagnar lagras även dem i databasen. Det är främst för dessa konton och kundvagnar webbplatsens resterande funktioner kretsar kring.
Som besökare sker navigeringen mellan webbsidorna inte genom direkta besök på HTML-dokumenten, utan här är det PHP-programmen man navigerar till som i sin tur laddar in HTML-dokumenten.
