(function () {

    console.log("Editor script loaded - attempting to register block");

wp.blocks.registerBlockType('custom/remote-login', {
    title: 'Remote Login',
    icon: 'admin-users',
    category: 'widgets',
    edit: () => {
        return wp.element.createElement(
            'div',
            { style: { padding: '20px', border: '1px solid #ddd' } },
            'Blocco di Login Remoto - Editor'
        );
    },
    save: () => {
        return null; // Usando il render callback PHP
    }
});
        console.log("Test file loaded successfully"); // Debug per verificare il caricamento

    document.addEventListener("DOMContentLoaded", function () {
        const loginButton = document.getElementById("login-button");
        const logoutButton = document.getElementById("logout-button");
        const usernameField = document.getElementById("username");
        const passwordField = document.getElementById("password");
        const messageContainer = document.getElementById("login-message");
        //const redirectAfterLogin = document.getElementById("members-area-url").value;

        // Definisce l'URL di reindirizzamento dopo il login
        const redirectUrl = document.getElementById("members-area-url").value;
        //"https://www.sifact.it/area-soci/";

        // Funzione per mostrare il messaggio
        const displayMessage = (message, isSuccess) => {
            if (messageContainer) {
                messageContainer.textContent = message;
                messageContainer.classList.remove("alert-success", "alert-danger");

                if (isSuccess) {
                    messageContainer.classList.add("alert-success");
                } else {
                    messageContainer.classList.add("alert-danger");
                }
            }
        };

        // Funzione per gestire il login
        const handleLogin = async () => {
            console.log("Login button clicked"); // Debug per confermare l'evento click

            const username = usernameField ? usernameField.value : "";
            const password = passwordField ? passwordField.value : "";

            if (!username || !password) {
                displayMessage("Per favore, inserisci nome utente e password.", false);
                return;
            }

            try {
                console.log("Sending login request"); // Debug per confermare la richiesta
                const response = await fetch('/wp-json/remote-login/v1/authenticate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password }),
                });

                const result = await response.json();

                if (response.ok) {
                    displayMessage(`Login effettuato con successo! Benvenuta/o, ${result.name}`, true);
                    window.location.href = redirectUrl;
                } else {
                    displayMessage(`Errore: ${result.message}`, false);
                }
            } catch (error) {
                console.error("Errore durante il login:", error);
                displayMessage("Errore nel login. Per favore, riprova.", false);
            }
        };

        // Funzione per gestire il logout
        const handleLogout = async () => {
            if (!confirm("Sei sicura/o di voler effettuare il logout?")) {
                return; // Interrompe la funzione se l'utente annulla
            }        
            try {
                console.log("Sending logout request"); // Debug per confermare la richiesta di logout
                const response = await fetch('/wp-json/remote-login/v1/logout', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': remoteLoginData ? remoteLoginData.nonce : "",
                    },
                });

                const result = await response.json();

                if (response.ok) {
                    displayMessage(result.message, true);
                    window.location.reload();
                } else {
                    displayMessage("Errore nel logout. Per favore, riprova.", false);
                }
            } catch (error) {
                console.error("Error during logout request:", error);
                displayMessage("Si è verificato un errore nel logout.", false);
            }
        };

        // Aggiunta dei listener per i pulsanti login e logout
        if (loginButton) {
            loginButton.addEventListener("click", handleLogin);
        } else {
            console.log("Login button not found"); // Debug per assicurarci che il pulsante sia presente
        }

        if (logoutButton) {
            logoutButton.addEventListener("click", handleLogout);
        }
        // Aggiunge il listener di logout al link dentro ogni elemento <li> con classe LogoutButtonMenu
        document.querySelectorAll('.logout-button-menu').forEach(li => {
            const logoutLink = li.querySelector('a'); // Seleziona il <a> all'interno del <li>
            if (logoutLink) {
                logoutLink.addEventListener("click", (event) => {
                    event.preventDefault(); // Previene il comportamento predefinito del link
                    handleLogout();
                });
            }
        });
    });
})();
