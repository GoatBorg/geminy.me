<p align="center">
  <img src="https://raw.githubusercontent.com/GoatBorg/geminy.me/refs/heads/main/755/stable/ios.png" alt="Geminy Banner" width="300"/>
</p>

<h1 align="center"></h1>
<p align="center"><i>Seni tanımıyoruz. Bu yüzden seni seviyoruz.</i></p>

<p align="center">
  <a href="https://geminyask.unaux.com/index.php">
    <img src="https://img.shields.io/badge/WEB_ADRES-İncele-ff2d72?style=for-the-badge&logo=heart&logoColor=white" alt="Demo"/>
  </a>
  <img src="https://img.shields.io/badge/VERSION-V8.0-7c3aed?style=for-the-badge&logo=sparkles&logoColor=white"/>
  <img src="https://img.shields.io/badge/STATUS-PRODUCTION_READY-22c55e?style=for-the-badge"/>
</p>

---

## 🎭 Ne Bu Şey?

> *Platonik aşıkların, kimlik dayatmalarından bıkanların,
>
> "sen kimsin lan" sorusuna "kimse"
> diye cevap vermek isteyenlerin kalesi.*

`Meta` senden fotoğraf istiyor. 

`Twitter` senden telefon numarası istiyor. 

`LinkedIn` senden özgeçmiş istiyor.

**geminy.me** sadece bir 
kullanıcı adı istiyor. 

Anonim soru sor, anonim mesaj at, kimse bilmesin. Özgürlük bu kadar basit.

---

## ✨ Özellikler

| Özellik | Açıklama |
|---|---|
| 💬 **Anonim Soru** | Kim attı? Bilinmez. Neden attı? O da bilinmez. |
| ❤️ **Beğeni Sistemi** | IP bazlı, giriş şart değil |
| 👁️ **Profil Görüntülenme** | Son 7 gün |
| 🏆 **Seviye Sistemi** | Yeşil → Sarı → Mavi → Mor → ⚡ Efsane |
| 👤 **Gizli Takip** | Takip listeni sadece sen görürsün |
| 🔒 **Gizli Hesap** | İstersen kimse seni bulamasın |
| 💌 **Mesajlaşma** | WhatsApp hissiyatlı, ama anonim ruhlu |
| 🔐 **2FA** | güvenlik şakaya gelmez |
| 📧 **SMTP E-posta** | Şifre sıfırlama, hesap silme onayı |
| 🗑️ **Hesap Silme** | iz bırakmak zorunda değilsin |
| 🎵 **Profil Müzik Kartı** | Ruhunu müzikle anlat |
| 🔗 **Sosyal Profiller** | Instagram, TikTok, Twitter — istersen hepsini ekle |
| 🌐 **Public API** | JSON döner |

---

## 🏆 Seviye Sistemi

Yanıt verdikçe, soru aldıkça, beğeni topladıkça yükseliyorsun:

```
Statü Seviyesi ❤️
```

| Puan | Seviye |
|---|---|
| 5+ | 🟢 Yeşil Tık |
| 25+ | 🟡 Sarı Tık |
| 80+ | 🔵 Mavi Tık |
| 200+ | 🟣 Mor Tık |
| 500+ | ⚡ Efsane |

---

## 🌐 Public API

Key yok. Auth yok. Sadece istek at, JSON al.

```bash
# Kullanıcı bilgisi
GET /app/api/users.php?u=kullaniciadi

# Sorular & Yanıtlar
GET /app/api/tweet.php?u=kullaniciadi
GET /app/api/tweet.php?u=kullaniciadi&page=2
```

---

## 🛠 Kurulum

> Karmaşık değil. Gerçekten.

**Adım 1 — Veritabanı**
```
install/ezyro_41408315_plus.sql → phpMyAdmin'den içe aktar
```

**Adım 2 — Yapılandırma**
```php
// app/config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'veritabani_adin');
define('DB_USER', 'kullanici_adin');
define('DB_PASS', 'sifren');
define('SITE_URL', 'https://siteadresin.com');
```

**Adım 3 — SMTP (E-posta)**
```php
define('SMTP_HOST', 'smtp.mailersend.net');
define('SMTP_PORT', 25);   // ⚠️ 587 çalışmazsa 25 yap
define('SMTP_USER', 'kullanici@domain.com');
define('SMTP_PASS', 'smtp_sifren');
```

**Adım 4 — Yükle**
```
Dosyaları public_html klasörüne at, işin bitti.
```

> **Gereksinimler:** PHP 7.4+, Apache/Nginx, `mod_rewrite` aktif ✓

---

## ⚠️ Sık Karşılaşılan Sorunlar

```
❌ "İşleme Alınamıyor" hatası
✅ .htaccess dosyasının sunucuda aktif olduğunu kontrol et
   

❌ Mail gitmiyor
✅ SMTP_PORT'u 25 yap — shared hosting'lerde 587 bloklu olur

❌ "Table already exists" SQL hatası  
✅ install/ klasöründeki _fixed.sql dosyasını kullan
   

❌ 500 Hatası
✅ app/config.php'de DB bilgilerin doğru mu kontrol et
```

---

## 🛡 Güvenlik

- `.htaccess` ile `app/` klasörüne direkt erişim kapalı
  
- `install/` dizini production'da erişilemez
  
- Brute-force koruması aktif
- Şifre sıfırlama token'ları hash'li
- 2FA kodları bcrypt ile saklanıyor
- SQL Injection query string koruması
- Kötü bot engeli (sqlmap, nikto, vb.)

---

## 🐈 Credits

**Bu proje, geleceğin teknolojisi ve dijital kankalarımın zekasıyla ilmek ilmek işlendi:**

| AI | Rol |
|---|---|
| **Claude**  | v3 → v8 mimarisi, güvenlik sistemi, API, seviye motoru, tüm kritik altyapı |
| **Copilot**  | Kod tamamlama, küçük refactoring'ler |
| **Gemini**  | Fikir geliştirme, alternatif yaklaşımlar |
| **Manus AI🐾**  | İlk prototip katkıları |

---
### Kararlı Beta Sürümler 
Telegram : [Tıkla Bana](https://t.me/c/3592883866/10)


### En Güncel Versionlar 
`Dikkat`
[Realase Menü](https://github.com/GoatBorg/geminy.me/releases/)
```
Lütfen En Sağlıklı Ve Fixlenmiş
Versionlar Bu Menüde
Sunulmaktadır
```
> `V9.5+` Sürümler İçin Devasa
> Güncelleme Filtre Özelliği
> Getirildi Yoksa
> Başımıza Çullanıyorlar


> `V10.0+` Sürümler İçin Devasa
> 🎭 8 farklı mizahi mesaj havuzu yaptım,
> her sayfa yüklemesinde rastgele biri çıkıyor
> 
**🫠 "Hey, neden bu kadar darlıyorsun, bırak da kafasını yaşasın.**
> 
**🥃 "Belki kadehleri şaha kaldırıyordur, hiç müsait değildir.**
>
**🌿 "Şu an doğada otlanıyordur, belki de dumanlar arasında sen de kayboldun.**

`NOT :` 
**Eğerki Birinin Profilinde Bu Tarz Mesajlar Varsa Çok Sağolun Engellenmissin**


  <br/><br/>
  <sub>
    Built with 🥃 by <a href="https://github.com/GoatBorg">GoatBorg</a>
    &nbsp;·&nbsp;
    Powered by <b>Claude · Anthropic</b> 🌿
    &nbsp;·&nbsp;
    <i>"Dijital mahremiyet bir lüks değil, haktır."</i>
  </sub>
</p>
