{{-- See https://github.com/graphql/graphiql/blob/main/examples/graphiql-cdn/index.html. --}}
@php
    use MLL\GraphiQL\GraphiQLAsset;
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>GraphiQL</title>
    <style>
        body {
            margin: 0;
            overflow: hidden;
            /* in Firefox */
        }

        #graphiql {
            height: 100dvh;
        }

        #graphiql-loading {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
        }

        .docExplorerWrap {
            /* Allow scrolling, see https://github.com/graphql/graphiql/issues/3098. */
            overflow: auto !important;
        }

        /* Style pour le bouton d'authentification - amélioré */
        #auth-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            padding: 10px 18px;
            background-color: #2a7bd1;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.25);
            transition: all 0.3s ease;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }

        #auth-button:hover {
            background-color: #1b5fad;
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(0, 0, 0, 0.3);
        }

        #auth-button:active {
            transform: translateY(0px);
        }
    </style>
    <script src="{{ GraphiQLAsset::reactJS() }}"></script>
    <script src="{{ GraphiQLAsset::reactDOMJS() }}"></script>
    <link rel="stylesheet" href="{{ GraphiQLAsset::graphiQLCSS() }}" />
    <link rel="stylesheet" href="{{ GraphiQLAsset::pluginExplorerCSS() }}" />
    <link rel="shortcut icon" href="{{ GraphiQLAsset::favicon() }}" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>

<body>
    <!-- Bouton d'authentification repositionné -->
    <button id="auth-button">Chargement...</button>

    <div id="graphiql">
        <div id="graphiql-loading">Loading…</div>
    </div>

    <script src="{{ GraphiQLAsset::graphiQLJS() }}"></script>
    <script src="{{ GraphiQLAsset::pluginExplorerJS() }}"></script>
    <script>
        const fetcher = GraphiQL.createFetcher({
            url: '{{ $url }}',
            subscriptionUrl: '{{ $subscriptionUrl }}',
        });
        const explorer = GraphiQLPluginExplorer.explorerPlugin();

        function GraphiQLWithExplorer() {
            return React.createElement(GraphiQL, {
                fetcher,
                plugins: [
                    explorer,
                ],
                defaultHeaders: JSON.stringify({
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                }),
                // See https://github.com/graphql/graphiql/tree/main/packages/graphiql#props for available settings
            });
        }

        ReactDOM.render(
            React.createElement(GraphiQLWithExplorer),
            document.getElementById('graphiql'),
        );

        // Script pour gérer l'authentification
        document.addEventListener('DOMContentLoaded', function () {
            const authButton = document.getElementById('auth-button');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            // Vérifier si l'utilisateur est connecté
            function checkAuthStatus() {
                fetch('/api/user', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    credentials: 'include'
                })
                    .then(response => {
                        if (response.ok) {
                            return response.json().then(user => {
                                console.log('User data:', user); // Pour le débogage
                                // Vérification de la structure de l'objet user
                                const userName = user && typeof user.name === 'string' ? user.name : 'API';
                                authButton.textContent = `Déconnexion (${userName})`;
                                authButton.onclick = logout;
                            });
                        } else {
                            authButton.textContent = 'Se connecter en tant que API';
                            authButton.onclick = loginAsAPI;
                        }
                    })
                    .catch(error => {
                        console.error('Erreur de vérification d\'authentification:', error);
                        authButton.textContent = 'Se connecter en tant que API';
                        authButton.onclick = loginAsAPI;
                    });
            }

            // Fonction pour se déconnecter
            function logout() {
                fetch('/api/logout', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    credentials: 'include'
                })
                    .then(response => {
                        if (response.ok) {
                            authButton.textContent = 'Se connecter en tant que API';
                            authButton.onclick = loginAsAPI;
                        }
                    })
                    .catch(error => {
                        console.error('Erreur de déconnexion:', error);
                    });
            }

            // Fonction pour se connecter en tant qu'API
            function loginAsAPI() {
                fetch('/api/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        email: 'api@example.com',
                        password: 'password'
                    }),
                    credentials: 'include'
                })
                    .then(response => {
                        if (response.ok) {
                            return response.json().then(data => {
                                authButton.textContent = 'Déconnexion (API)';
                                authButton.onclick = logout;
                                // Rafraîchir la page pour mettre à jour les tokens d'authentification
                                window.location.reload();
                            });
                        } else {
                            console.error('Échec de connexion');
                        }
                    })
                    .catch(error => {
                        console.error('Erreur de connexion:', error);
                    });
            }

            // Vérifier le statut d'authentification au chargement
            checkAuthStatus();
        });
    </script>
</body>

</html>