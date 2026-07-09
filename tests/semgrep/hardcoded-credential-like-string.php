<?php

// ruleid: hardcoded-credential-like-string
$password = 'hunter2';

// ruleid: hardcoded-credential-like-string
$apiKey = 'sk_live_abc123';

// ruleid: hardcoded-credential-like-string
$secret = 'correct horse battery staple';

// ok: hardcoded-credential-like-string
$foo = 'bar';

// ok: hardcoded-credential-like-string
$notAVariable = 'just a string, not a credential';

// ok: hardcoded-credential-like-string
$password = env('DB_PASSWORD');
