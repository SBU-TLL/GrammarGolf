<?php
/**
 * Site entry point. The app ships three versions, each in its own directory:
 *   /public/      open to everyone, no login
 *   /brightspace/ LTI / Shibboleth authenticated, saves progress
 *   /admin/       course editor, admin roster only
 * The bare domain sends visitors to the public version.
 */
header('Location: /public/', true, 302);
