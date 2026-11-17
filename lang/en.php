<?php

declare(strict_types=1);

/**
 * English texts. This is the reference file: every key must exist here.
 * To add a language, copy this file to lang/{code}.php (e.g. de.php, pt-BR.php) and translate the values.
 * {name} placeholders are replaced at runtime. Keys starting with "js." are also sent to JavaScript.
 */
return [
    'app.scan_label' => 'Scan label',
    'common.log_out' => 'Log out',
    'common.cancel_to_freezer' => 'Cancel and return to freezer',

    'home.logged_in_to' => 'Logged in to:',
    'home.show_items' => 'Show items',
    'home.scan_prompt' => 'Scan your freezer label to get started.',

    'freezer.empty' => 'The freezer is empty. Scan a food label to add food.',
    'freezer.frozen' => 'Frozen: {date}',
    'freezer.label_code' => 'Label code',
    'freezer.logout_hint' => 'When you are done, log out so nobody else can change this freezer from this device.',
    'freezer.search_label' => 'Search items',
    'freezer.search_placeholder' => 'Search by food name or label code',
    'freezer.no_matches' => 'No food matches your search.',

    'setup.title' => 'New label',
    'setup.intro' => 'This label has not been used yet. Set it up as the label of a freezer, or, to add food, scan your freezer label first.',
    'setup.name_label' => 'Freezer name',
    'setup.submit' => 'Use as freezer label',
    'setup.error_name' => 'Enter a freezer name (up to {max} characters).',

    'add.title' => 'Add item',
    'add.freezer' => 'Freezer:',
    'add.label' => 'Label:',
    'add.food_name' => 'Food name',
    'add.date' => 'Date',
    'add.photo' => 'Photo (optional)',
    'add.save' => 'Save',
    'add.error_name' => 'Enter a food name (up to {max} characters).',
    'add.error_date' => 'Enter a valid date.',

    'consume.title' => 'Consumed?',
    'consume.submit' => 'Mark as consumed / clear tag',

    'error.label_invalid.title' => 'Label not valid',
    'error.label_invalid.message' => 'This label is not registered or is no longer active.',
    'error.freezer_not_scanned.title' => 'Freezer not scanned',
    'error.freezer_not_scanned.message' => 'Please scan your freezer label first.',
    'error.wrong_freezer.title' => 'Wrong freezer',
    'error.wrong_freezer.message' => 'This label belongs to a different freezer.',
    'error.back_to' => 'Back to {name}',
    'error.not_found.title' => 'Not found',
    'error.not_found.message' => 'The page you are looking for does not exist.',
    'error.method_not_allowed.title' => 'Method not allowed',
    'error.method_not_allowed.message' => 'This action is not allowed.',

    'photo.missing' => 'Please choose a photo.',
    'photo.upload_failed' => 'The photo could not be uploaded.',
    'photo.too_large' => 'The photo is too large.',
    'photo.invalid_type' => 'The file must be a JPEG, PNG or WebP image.',
    'photo.save_failed' => 'The photo could not be saved.',

    'scan.photo_button' => 'Take a photo of the QR code',
    'scan.switch_camera' => 'Switch camera',
    'scan.close' => 'Close',

    'photo.take_button' => 'Take photo with camera',
    'photo.capture_button' => 'Capture',
    'photo.clear_button' => 'Remove selected photo',
    'edit.photo_remove' => 'Remove the current photo',

    'js.scan_prompt' => "Point the camera at a label's QR code.",
    'js.scan_not_label' => 'That QR code is not a Buzlook label. Try again.',
    'js.scan_needs_https' => 'Live scanning needs HTTPS. Take a photo of the QR code instead.',
    'js.scan_camera_unavailable' => 'Camera not available. Take a photo of the QR code instead.',
    'js.scan_reading' => 'Reading QR code…',
    'js.scan_none_found' => 'No QR code found in the photo. Try again, closer and sharper.',
    'js.scan_read_failed' => 'The photo could not be read. Try again.',
    'js.compressing' => 'Compressing photo…',
    'js.image_unreadable' => 'The image could not be read.',
    'js.compress_failed' => 'Compression failed.',
    'js.photo_unprocessable' => 'The photo could not be processed.',

    // Quantity, note, voice note, edit, re-label, export, rate limit.
    'common.edit' => 'Edit',
    'item.quantity' => 'Quantity: {value}',

    'add.quantity' => 'Quantity (optional)',
    'add.quantity_placeholder' => 'e.g. 2 portions',
    'add.note' => 'Note (optional)',
    'add.voice' => 'Voice note (optional, up to {seconds} seconds)',
    'add.voice_file' => 'Choose or record an audio file',
    'add.error_quantity' => 'The quantity is too long (up to {max} characters).',
    'add.error_note' => 'The note is too long (up to {max} characters).',

    'voice.upload_failed' => 'The voice note could not be uploaded.',
    'voice.too_large' => 'The voice note is too large.',
    'voice.invalid_type' => 'The voice note must be an audio file.',

    'edit.title' => 'Edit item',
    'edit.photo_hint' => 'Leave empty to keep the current photo.',
    'edit.voice_current' => 'Current voice note',
    'edit.voice_remove' => 'Remove the voice note',
    'edit.voice_replace_hint' => 'Recording a new voice note replaces the current one.',
    'edit.new_label' => 'Move to another label',
    'edit.new_label_placeholder' => 'Label code',
    'edit.new_label_hint' => 'If this label is damaged or lost, enter or scan the code of a new, unused label. The item moves there and this label becomes free.',
    'edit.scan_button' => 'Scan',
    'edit.save' => 'Save changes',
    'edit.cancel' => 'Cancel',
    'edit.error_new_label' => 'That label cannot be used. It must be a registered, unused label (or a free food label of this freezer).',
    'consume.edit' => 'Edit item',

    'freezer.export_button' => 'Export labels (ZIP)',
    'freezer.export_hint' => 'Downloads QR images of this freezer label and all its food labels, to back them up or print them again.',

    'error.rate_limited.title' => 'Too many invalid scans',
    'error.rate_limited.message' => 'Too many unrecognized labels were scanned from your network. Please try again in {minutes} minute(s).',

    'js.voice_record' => 'Record voice note',
    'js.voice_stop' => 'Stop',
    'js.voice_clear' => 'Remove voice note',
    'js.voice_recording' => 'Recording… {seconds} s left',
    'js.voice_ready' => 'Voice note ready.',
    'js.voice_too_long' => 'The audio is longer than {max} seconds.',
    'js.voice_too_large' => 'The audio file is too large.',
    'js.voice_denied' => 'Microphone not available. Choose an audio file instead.',
    'js.export_working' => 'Creating labels…',
    'js.export_done' => 'Done: {count} labels downloaded.',
    'js.export_failed' => 'Export failed. Please try again.',
];
