<?php

// A stand-in for the local-only file every project accumulates. It must never reach an
// image: layers are readable, and a later `rm` does not remove it from image history.
return ['admin_token' => 'local-only-not-a-real-secret'];
