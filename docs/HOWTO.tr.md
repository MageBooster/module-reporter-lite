# MageBooster Reporter Lite: kullanım kılavuzu

Reporter Lite ile Magento yönetim panelinde SQL raporu yazar, çalıştırır, sonucu indirir ve belirlediğiniz takvimle e-postayla gönderirsiniz. Sorgular ayrı ve yalnızca okuma yetkisi olan bir veritabanı kullanıcısıyla çalışır; bir rapor mağaza verisini hiçbir şekilde değiştiremez.

## Neler yapar

- **SQL raporları sayfası:** **MageBooster > Reporter Lite > SQL reports** menüsünden açılır. Solda raporlarınız, sağda açık rapor ve **Query**, **Schedule**, **History** sekmeleri bulunur. Üstteki dört kart son çalışmayı, bir sonraki gönderimi, alıcı sayısını ve son sonucu gösterir.
- **Sorgu:** Sorguyu editöre yazıp **Run** düğmesine (ya da Ctrl+Enter) basın. İlk 100 satır, satır sayısı ve çalışma süresi görünür; ilk sütun bir etiket, başka bir sütun sayıysa çubuk grafik de çıkar. **Format** ana bölümleri ayrı satırlara alır.
- **Parametreler:** Sorguya `:ad` biçiminde yazdığınız her parametre için editörün altında bir alan açılır.
  - `:from`, `:to`, `:date`, `:start_date`, `:end_date` gibi adlar tarih kabul edilir; Bugün, Dün, Son 7 gün, Bu ayın başı gibi hazır seçenekler ya da elle seçilen bir tarih kullanılır. Başlangıç tarihi o günün 00:00'ı, bitiş tarihi 23:59:59'u olarak mağaza saatine göre alınır ve Magento'nun tarihleri sakladığı gibi UTC'ye çevrilir.
  - `:store` (ya da `:store_id`) için mağaza görünümü listesi gelir.
  - Diğer adlar düz metin alanıdır.
- **İndirme:** CSV, JSON ve HTML her zaman; `phpoffice/phpspreadsheet` kuruluysa **Excel**; `dompdf/dompdf` kuruluysa **PDF**. İndirilen dosyada ekranda gösterilen 100 satır değil, satır sınırına kadar bütün satırlar bulunur.
- **Yeni rapor:** Boş bir editör açar. Örneklerden birini seçerseniz (Sales by day, Best sellers, Customers with no orders, Low stock, Abandoned carts, Coupon usage) editör dolar; **About this example** kutusu örneğin ne döndürdüğünü ve nasıl değiştirileceğini anlatır. Sağ sütunda sorgu yazma adımları, hangi verinin hangi tabloda olduğu ve sık yapılan hatalar yer alır. Kaydedilmemiş değişiklikle sayfadan çıkarken onay istenir.
- **Duplicate** açık raporun kaydedilmemiş bir kopyasını oluşturur.
- **Zamanlama:** **On** ile açın; günlük, haftalık, aylık ya da cron ifadesi seçin, ardından gün ve saati (mağaza saat dilimi) ve verinin hangi dönemi kapsayacağını (örneğin "The previous week") belirleyin. **Next send** satırı bir sonraki gönderim tarihini ve kapsadığı dönemi gösterir. Alıcıları ekleyin, dosya türünü ve dosyanın ek olarak mı yoksa 7 gün geçerli bir indirme bağlantısıyla mı gideceğini seçin. **Don't send when there are no rows** işaretliyse boş sonuç gönderilmez. **Send a test to me** raporu hemen kendi yönetici e-postanıza gönderir.
- **Geçmiş:** Zamanlanmış ve test çalışmalarının hepsi sonucu, satır sayısı ve dosyasıyla listelenir. Dosyalar saklandıkları sürece indirilebilir.

## Gereksinimler

- PHP 8.0 veya üstü
- Magento 2.4.4 - 2.4.9 (2.4.8-p5 / PHP 8.3 ve 2.4.9 / PHP 8.4 üzerinde test edildi)
- MySQL 5.7+ / 8 ya da MariaDB 10.3+
- `magebooster/module-license` 1.1 veya üstü (Composer kendiliğinden kurar)
- İsteğe bağlı, Excel için: `composer require phpoffice/phpspreadsheet`
- İsteğe bağlı, PDF için: `composer require dompdf/dompdf`

Reporter Lite yalnızca yönetim panelinde çalışır. Ayrı bir Hyvä paketi yoktur; aynı paket Luma ve Hyvä mağazalarında çalışır.

## Composer ile kurulum (ücretli)

1. Depoyu ekleyin:

   ```bash
   composer config repositories.magebooster composer https://repo.magebooster.com
   ```

2. Magento kök klasöründeki `auth.json` dosyasına erişim bilgilerinizi yazın. Kullanıcı adı sipariş e-postanızdadır, parola seri numaranızdır:

   ```json
   {
     "http-basic": {
       "repo.magebooster.com": {
         "username": "<sipariş e-postasındaki kullanıcı adı>",
         "password": "<seri numaranız>"
       }
     }
   }
   ```

3. Paketi kurun:

   ```bash
   composer require magebooster/module-reporter-lite
   ```

`auth.json` dosyasını sürüm kontrolüne eklemeyin.

## Zip dosyasından kurulum

1. License modülünü `app/code/MageBooster/License` klasörüne açın.
2. Bu modülü `app/code/MageBooster/ReporterLite` klasörüne açın.
3. Aşağıdaki komutları çalıştırın.

## Modülü etkinleştirme

```bash
bin/magento module:enable MageBooster_License MageBooster_ReporterLite
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

Şu görevler için Magento cron'u çalışıyor olmalı:

- `mbreporterlite_dispatch`, her dakika: zamanı gelen raporları gönderir
- `mbreporterlite_cleanup`, her gün 03:17'de: süresi dolan dosyaları ve eski geçmişi siler

## Salt okunur veritabanı kullanıcısını oluşturma

Reporter Lite ana Magento veritabanı kullanıcısını asla kullanmaz. Yalnızca okuyabilen bir kullanıcı oluşturup bilgilerini `app/etc/env.php` dosyasına yazın.

1. Kullanıcıyı oluşturun ve yalnızca `SELECT` yetkisi verin. `magento` yerine kendi veritabanı adınızı yazın:

   ```sql
   CREATE USER 'mb_reports'@'localhost' IDENTIFIED BY '<güçlü parola>';
   GRANT SELECT ON magento.* TO 'mb_reports'@'localhost';
   FLUSH PRIVILEGES;
   ```

   Veritabanı başka bir sunucudaysa `localhost` yerine o adresi yazın (örneğin `'mb_reports'@'10.0.0.%'`).

   Bazı verileri erişim dışında tutmak isterseniz `magento.*` yerine yalnızca istediğiniz tablolara, her tablo için bir satırla yetki verin (`GRANT SELECT ON magento.sales_order TO ...`). Gizli bilgi içeren tabloları, örneğin `customer_entity` (parola özetleri) ya da `admin_user`, ya hiç eklemeyin ya da yalnızca bazı sütunlarına izin verin: `GRANT SELECT (entity_id, email, firstname, lastname, created_at, group_id, store_id) ON magento.customer_entity TO 'mb_reports'@'localhost';`

2. Kullanıcıyı `app/etc/env.php` dosyasına, dizinin en üst düzeyine ekleyin:

   ```php
   'mbreporterlite' => [
       'sql' => [
           'username' => 'mb_reports',
           'password' => '<güçlü parola>',
           // isteğe bağlı; yazılmazsa ana bağlantının host ve dbname değerleri kullanılır:
           // 'host' => 'localhost',
           // 'dbname' => 'magento',
       ],
   ],
   ```

   MageBooster Reports modülü kuruluysa ve `mbreports/sql` altında bir salt okunur kullanıcısı varsa, `mbreporterlite/sql` tanımlı değilken Reporter Lite o kullanıcıyı kullanır.

3. `bin/magento cache:flush` komutunu çalıştırın.

Sayfa başlığı durumu gösterir: **Read-only database user · connected** (yeşil), kullanıcı veri değiştirebiliyorsa bir uyarı, bağlanamıyorsa ya da tanımlı değilse bir mesaj. Kullanıcı adı ana Magento kullanıcısından farklı olmalıdır; aynıysa sorgular reddedilir.

## Sorgu kuralları

- Tek bir `SELECT` ya da `WITH ... SELECT` ifadesi. Sondaki `;` sorun değildir; ikinci bir ifade reddedilir.
- İzin verilmeyenler: `INSERT`, `UPDATE`, `DELETE` ve veri yazan ya da yönetimsel diğer ifadeler, `INTO OUTFILE` / `DUMPFILE`, `LOAD_FILE`, `SLEEP`, `BENCHMARK`, kilit fonksiyonları, değişkenler (`@x`, `:=`), sürüm yorumları (`/*! */`), `FOR UPDATE` / `LOCK IN SHARE MODE`, `information_schema`, `performance_schema`, `mysql` ve `sys`.
- `?` yerine adlı parametre (`:from`) kullanın.
- Tablo adlarını önek olmadan yazın; önek `FROM` ve `JOIN` sonrasındaki tablolara kendiliğinden eklenir. Virgüllü listede (`FROM a, b`) yalnızca ilk tablo önek alır; bu yüzden `JOIN` kullanın.
- Her çalışma süre sınırında (varsayılan 10 saniye) durur ve en fazla satır sınırı kadar (varsayılan 5.000) satır okur. Sınıra ulaşıldığında sayı "more than 5000" olarak görünür.
- Magento tarihleri UTC olarak saklar. Tarih parametreleri sizin için çevrilir; bir tarihi mağaza saatiyle göstermek için kendi farkınızla `CONVERT_TZ(created_at, '+00:00', '+03:00')` kullanın.

## Yönetim ayarları

**Stores > Configuration > MageBooster > Reporter Lite**:

- **E-mail:** Send from (mağaza e-posta kimliği); Largest attachment (MB), varsayılan 10. Daha büyük dosyalar indirme bağlantısıyla gönderilir.
- **Queries:** Most rows per run (varsayılan 5.000, en fazla 100.000); Longest run (saniye, varsayılan 10, en fazla 120).
- **Files and history:** Download links work for (gün), varsayılan 7; Keep run history for (gün), varsayılan 90.

### Yetkiler (ACL)

**System > Permissions > User Roles > Role Resources > MageBooster > Reporter Lite** altında:

- **Open and run saved reports, download results:** sayfa, kayıtlı raporları çalıştırma ve indirme.
- **Write, save and delete report queries:** editör, Yeni rapor, Duplicate, Save ve Delete. Bu yetki yoksa kullanıcı kayıtlı sorguları yalnızca olduğu gibi çalıştırabilir.
- **Schedule reports and send them by e-mail:** Schedule sekmesi ve test gönderimi.
- Ayarlar sayfasının **Stores > Configuration** altında kendi yetkisi vardır.

## Geliştiriciler için

### Komut satırı

```bash
bin/magento mbreporterlite:run                      # raporları listeler
bin/magento mbreporterlite:run 3                    # 3 numaralı raporu şimdi çalıştırıp alıcılarına gönderir
bin/magento mbreporterlite:run 3 --test-to=ben@ornek.com
bin/magento mbreporterlite:run 3 --test-to=ben@ornek.com --eml=var/mail-test   # e-postayı .eml olarak yazar, göndermez
bin/magento mbreporterlite:run --cleanup            # süresi dolan dosyaları ve eski geçmişi siler
```

### Veritabanı tabloları

- `mbreporterlite_report`: raporlar, parametreler ve zamanlamalar
- `mbreporterlite_run`: çalışma geçmişi, dosya adları, özetlenmiş indirme anahtarları ve son geçerlilik tarihleri

Dosyalar `var/mbreporterlite` klasöründe tutulur. Herkese açık indirme adresi `https://<mağaza>/mbreporterlite/file/get/t/<anahtar>/` biçimindedir; arama motorlarında dizine eklenmez, bağlantının süresi dolunca ya da dosya temizlenince çalışmaz.

## Sorun giderme

- **"Reporter Lite is not set up yet".** `env.php` dosyasına `mbreporterlite/sql` bölümünü ekleyip önbelleği temizleyin.
- **"Could not connect as the read-only report user".** Kullanıcı adını, parolayı ve yetkideki adresi (`'mb_reports'@'localhost'` ya da `'%'`) kontrol edin.
- **Bir tablo için "SELECT command denied".** Salt okunur kullanıcının o tabloya yetkisi yok. Yetki verin ya da tabloyu sorgudan çıkarın.
- **Başlık, kullanıcının veri değiştirebildiği uyarısını veriyor.** Bütün yetkileri geri alıp yalnızca `SELECT` verin: `REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'mb_reports'@'localhost'; GRANT SELECT ON magento.* TO 'mb_reports'@'localhost';`
- **Zamanlanmış raporlar gelmiyor.** Cron'un çalıştığını, **Send from** kimliğini ve mağazanın e-posta ayarlarını (Stores > Configuration > Advanced > System > Mail Sending Settings) kontrol edin. History sekmesi başarısız her çalışmanın nedenini gösterir.
- **Excel ya da PDF düğmesi yok.** `phpoffice/phpspreadsheet` ya da `dompdf/dompdf` paketini kurun.
- **Sorgu zaman aşımına uğruyor.** Tarih aralığını daraltın, dizinli bir sütuna (`created_at`, `entity_id`, `store_id`) `WHERE` ekleyin ya da **Longest run** değerini artırın.

## Kaldırma

```bash
bin/magento module:disable MageBooster_ReporterLite
composer remove magebooster/module-reporter-lite
bin/magento setup:upgrade
bin/magento cache:flush
```

Artık gerekmiyorsa `mbreporterlite_report` ve `mbreporterlite_run` tablolarını silin, `var/mbreporterlite` klasörünü kaldırın, salt okunur veritabanı kullanıcısını silin ve `env.php` içindeki `mbreporterlite` bölümünü çıkarın.

## Lisans kontrolü (seri numarası)

- Seri numarasını **Stores > Configuration > MageBooster > Licenses** sayfasındaki **MageBooster Reporter Lite** alanına girin. Seri numarası `MB1` ile başlar.
- Aynı sayfadaki **License status** tablosu modülü **Active**, **Grace period** ya da **Switched off** olarak gösterir. `bin/magento magebooster:license:status` aynı bilgiyi verir.
- Geçerli bir seri numarası yoksa (girilmemiş, geçersiz, süresi dolmuş, başka alan adına ya da başka ürüne ait) modül **30 gün** çalışmaya devam eder. Bu süre, mağaza modülü geçerli seri numarası olmadan ilk gördüğünde başlar. Geçerli bir seri numarası süreyi sona erdirir.
- Bu süre boyunca ve sonrasında her yönetim sayfasında kapatılamayan bir uyarı görünür; kalan gün sayısını ya da modülün kapandığı tarihi gösterir.
- Müşteriler hiçbir lisans mesajı görmez; kontrol mağazanızın içinde yapılır, MageBooster'a hiçbir şey gönderilmez.
- Geliştirme adresleri (`localhost`, `127.0.0.1`, `*.localhost`, `*.test`, `*.local`) alan adı kontrolünden her zaman geçer.
- **30 gün dolduktan sonra:** SQL raporları sayfası "license required" mesajıyla Licenses sayfasına yönlendirir, zamanlanmış raporlar gönderilmez ve indirme bağlantıları çalışmaz. Raporlar ve geçmiş silinmez; geçerli bir seri numarasıyla geri gelir.

## Teşekkür

Kodhub Reporter'dan (MIT) esinlenilmiştir. Reporter Lite sıfırdan yazılmıştır.

## Lisans ve kopyalama

Ticari lisans, bkz. `LICENSE.txt`. Telif hakkı (c) 2026 WebWorks 101 OÜ (MageBooster). Tüm hakları saklıdır. Koşulların tamamı README dosyasındadır.

## Destek

info@magebooster.com
