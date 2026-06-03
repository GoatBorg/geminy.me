# geminy.me v4 Fix Codebase

Bu paket, mevcut PHP/PDO tabanlı geminy.me projesinin v4 düzenlemesidir. `SQL/new.sql` doğrudan phpMyAdmin üzerinden içe aktarılabilir. Kod; modern iOS/Instagram hissi, gizlilik odaklı canlı akış, AJAX arama, 2FA, şifre sıfırlama, yedek SMTP yapısı, profil şarkısı ve güvenli `.htaccess` kuralları içerir.

## Kurulum

Önce `SQL/new.sql` dosyasını veritabanına içe aktar. Ardından `app/config.php` içinde `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` ve `SITE_URL` değerlerini kendi sunucuna göre kontrol et. Dosyaları hosting `htdocs` klasörüne yükle.

## Önemli not

SMTP bilgileri kullanıcının verdiği şekilde `app/config.php` ve `SQL/new.sql` içine eklendi. Gerçek yayında bu şifreyi düzenli olarak yenilemen ve herkese açık repo içine koymaman önerilir.
