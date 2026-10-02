<?php

return [
    'project_id' => env('FIREBASE_PROJECT_ID', 'fintronixmicrofinance-c2656'),

    /*
    | Path to the Firebase Admin SDK service-account JSON.
    | Relative paths are resolved from the project root.
    */
    'credentials' => env(
        'FIREBASE_CREDENTIALS',
        'storage/app/firebase/fintronixmicrofinance-c2656-firebase-adminsdk-fbsvc-7d456d1b6f.json'
    ),
];
