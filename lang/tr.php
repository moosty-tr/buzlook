<?php

declare(strict_types=1);

/**
 * Türkçe metinler (Turkish texts). Keys must match lang/en.php.
 */
return [
    'app.scan_label' => 'Etiket tara',
    'common.log_out' => 'Çıkış yap',
    'common.cancel_to_freezer' => 'Vazgeç ve dondurucuya dön',

    'home.logged_in_to' => 'Bağlı dondurucu:',
    'home.show_items' => 'Ürünleri göster',
    'home.scan_prompt' => 'Başlamak için dondurucu etiketinizi okutun.',

    'freezer.empty' => 'Dondurucu boş. Yiyecek eklemek için bir yiyecek etiketi okutun.',
    'freezer.frozen' => 'Dondurulma: {date}',
    'freezer.label_code' => 'Etiket kodu',
    'freezer.logout_hint' => 'İşiniz bittiğinde çıkış yapın; böylece bu cihazdan başka kimse bu dondurucuyu değiştiremez.',
    'freezer.search_label' => 'Ürünlerde ara',
    'freezer.search_placeholder' => 'Yiyecek adı veya etiket koduyla ara',
    'freezer.no_matches' => 'Aramanızla eşleşen yiyecek yok.',

    'setup.title' => 'Yeni etiket',
    'setup.intro' => 'Bu etiket henüz kullanılmadı. Onu bir dondurucunun etiketi olarak ayarlayın; yiyecek eklemek için önce dondurucu etiketinizi okutun.',
    'setup.name_label' => 'Dondurucu adı',
    'setup.submit' => 'Dondurucu etiketi olarak kullan',
    'setup.error_name' => 'Bir dondurucu adı girin (en fazla {max} karakter).',

    'add.title' => 'Ürün ekle',
    'add.freezer' => 'Dondurucu:',
    'add.label' => 'Etiket:',
    'add.food_name' => 'Yiyecek adı',
    'add.date' => 'Tarih',
    'add.photo' => 'Fotoğraf (isteğe bağlı)',
    'add.save' => 'Kaydet',
    'add.error_name' => 'Bir yiyecek adı girin (en fazla {max} karakter).',
    'add.error_date' => 'Geçerli bir tarih girin.',

    'consume.title' => 'Tüketildi mi?',
    'consume.submit' => 'Tüketildi olarak işaretle / etiketi temizle',

    'error.label_invalid.title' => 'Geçersiz etiket',
    'error.label_invalid.message' => 'Bu etiket kayıtlı değil veya artık etkin değil.',
    'error.freezer_not_scanned.title' => 'Dondurucu okutulmadı',
    'error.freezer_not_scanned.message' => 'Lütfen önce dondurucu etiketinizi okutun.',
    'error.wrong_freezer.title' => 'Yanlış dondurucu',
    'error.wrong_freezer.message' => 'Bu etiket başka bir dondurucuya ait.',
    'error.back_to' => '{name} sayfasına dön',
    'error.not_found.title' => 'Bulunamadı',
    'error.not_found.message' => 'Aradığınız sayfa mevcut değil.',
    'error.method_not_allowed.title' => 'İzin verilmeyen işlem',
    'error.method_not_allowed.message' => 'Bu işleme izin verilmiyor.',

    'photo.missing' => 'Lütfen bir fotoğraf seçin.',
    'photo.upload_failed' => 'Fotoğraf yüklenemedi.',
    'photo.too_large' => 'Fotoğraf çok büyük.',
    'photo.invalid_type' => 'Dosya bir JPEG, PNG veya WebP görseli olmalıdır.',
    'photo.save_failed' => 'Fotoğraf kaydedilemedi.',

    'scan.photo_button' => 'QR kodunun fotoğrafını çek',
    'scan.switch_camera' => 'Kamerayı değiştir',
    'scan.close' => 'Kapat',

    'photo.take_button' => 'Kamerayla fotoğraf çek',
    'photo.capture_button' => 'Çek',
    'photo.clear_button' => 'Seçilen fotoğrafı kaldır',
    'edit.photo_remove' => 'Mevcut fotoğrafı kaldır',

    'js.scan_prompt' => 'Kamerayı bir etiketin QR koduna doğrultun.',
    'js.scan_not_label' => 'Bu QR kodu bir Buzlook etiketi değil. Tekrar deneyin.',
    'js.scan_needs_https' => 'Canlı tarama için HTTPS gerekir. Bunun yerine QR kodunun fotoğrafını çekin.',
    'js.scan_camera_unavailable' => 'Kamera kullanılamıyor. Bunun yerine QR kodunun fotoğrafını çekin.',
    'js.scan_reading' => 'QR kodu okunuyor…',
    'js.scan_none_found' => 'Fotoğrafta QR kodu bulunamadı. Daha yakından ve net şekilde tekrar deneyin.',
    'js.scan_read_failed' => 'Fotoğraf okunamadı. Tekrar deneyin.',
    'js.compressing' => 'Fotoğraf sıkıştırılıyor…',
    'js.image_unreadable' => 'Görsel okunamadı.',
    'js.compress_failed' => 'Sıkıştırma başarısız oldu.',
    'js.photo_unprocessable' => 'Fotoğraf işlenemedi.',

    // Adet, not, sesli not, düzenleme, etiket değiştirme, dışa aktarma, hız sınırı.
    'common.edit' => 'Düzenle',
    'item.quantity' => 'Miktar: {value}',

    'add.quantity' => 'Miktar (isteğe bağlı)',
    'add.quantity_placeholder' => 'örn. 2 porsiyon',
    'add.note' => 'Not (isteğe bağlı)',
    'add.voice' => 'Sesli not (isteğe bağlı, en fazla {seconds} saniye)',
    'add.voice_file' => 'Ses dosyası seç veya kaydet',
    'add.error_quantity' => 'Miktar çok uzun (en fazla {max} karakter).',
    'add.error_note' => 'Not çok uzun (en fazla {max} karakter).',

    'voice.upload_failed' => 'Sesli not yüklenemedi.',
    'voice.too_large' => 'Sesli not çok büyük.',
    'voice.invalid_type' => 'Sesli not bir ses dosyası olmalıdır.',

    'edit.title' => 'Ürünü düzenle',
    'edit.photo_hint' => 'Mevcut fotoğrafı korumak için boş bırakın.',
    'edit.voice_current' => 'Mevcut sesli not',
    'edit.voice_remove' => 'Sesli notu kaldır',
    'edit.voice_replace_hint' => 'Yeni bir sesli not kaydetmek mevcut olanın yerini alır.',
    'edit.new_label' => 'Başka bir etikete taşı',
    'edit.new_label_placeholder' => 'Etiket kodu',
    'edit.new_label_hint' => 'Bu etiket hasar gördüyse veya kaybolduysa, yeni ve kullanılmamış bir etiketin kodunu girin veya okutun. Ürün oraya taşınır ve bu etiket boşa çıkar.',
    'edit.scan_button' => 'Okut',
    'edit.save' => 'Değişiklikleri kaydet',
    'edit.cancel' => 'Vazgeç',
    'edit.error_new_label' => 'Bu etiket kullanılamaz. Kayıtlı ve kullanılmamış bir etiket (veya bu dondurucunun boş bir yiyecek etiketi) olmalıdır.',
    'consume.edit' => 'Ürünü düzenle',

    'freezer.export_button' => 'Etiketleri dışa aktar (ZIP)',
    'freezer.export_hint' => 'Bu dondurucu etiketinin ve tüm yiyecek etiketlerinin QR görsellerini yedeklemek veya yeniden yazdırmak için indirir.',

    'error.rate_limited.title' => 'Çok fazla geçersiz okutma',
    'error.rate_limited.message' => 'Ağınızdan çok sayıda tanınmayan etiket okutuldu. Lütfen {minutes} dakika sonra tekrar deneyin.',

    'js.voice_record' => 'Sesli not kaydet',
    'js.voice_stop' => 'Durdur',
    'js.voice_clear' => 'Sesli notu kaldır',
    'js.voice_recording' => 'Kaydediliyor… {seconds} sn kaldı',
    'js.voice_ready' => 'Sesli not hazır.',
    'js.voice_too_long' => 'Ses kaydı {max} saniyeden uzun.',
    'js.voice_too_large' => 'Ses dosyası çok büyük.',
    'js.voice_denied' => 'Mikrofon kullanılamıyor. Bunun yerine bir ses dosyası seçin.',
    'js.export_working' => 'Etiketler oluşturuluyor…',
    'js.export_done' => 'Tamamlandı: {count} etiket indirildi.',
    'js.export_failed' => 'Dışa aktarma başarısız oldu. Lütfen tekrar deneyin.',
];
