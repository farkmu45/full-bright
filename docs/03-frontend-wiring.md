# Memasang Tracking pada Frontend

**Wiring frontend** berarti menghubungkan elemen UI dengan tracking yang sudah disediakan. Tracking otomatis dipasang oleh `TrackingLayout` pada semua halaman Inertia. Developer hanya perlu memakai wrapper yang benar pada CTA dan form.

## Aturan wajib

1. CTA menggunakan `TrackedCTA`.
2. Form lead menggunakan `TrackedForm`.
3. Section penting menggunakan elemen `<section>` dengan `id` unik.
4. Jangan mengirim event `lead` atau `payment` dari browser.
5. Jangan mengganti nama event resmi.

## CTA WhatsApp

```tsx
import { TrackedCTA } from '@/components/tracking/TrackedCTA';

export function PricingWhatsApp({ url }: { url: string }) {
    return (
        <TrackedCTA
            zone="pricing"
            action="whatsapp"
            label="Chat Sekarang"
            href={url}
        >
            Chat Sekarang
        </TrackedCTA>
    );
}
```

Pada mode CTWA, contoh ini menghasilkan `whatsapp_lead` sebelum browser membuka WhatsApp.

## CTA menuju section

```tsx
<TrackedCTA
    zone="hero"
    action="scroll"
    label="Lihat Paket"
    href="#pricing"
>
    Lihat Paket
</TrackedCTA>
```

Contoh ini menghasilkan `intent`, lalu browser menuju section dengan `id="pricing"`.

## Checkout eksternal pada CTWA

```tsx
<TrackedCTA
    zone="pricing"
    action="external_checkout"
    label="Checkout"
    href={externalCheckoutUrl}
>
    Checkout
</TrackedCTA>
```

Pada mode CTWA, contoh ini menghasilkan `direct_checkout`.

## Tautan biasa yang ingin diukur

```tsx
<TrackedCTA
    zone="faq"
    action="link"
    label="Lihat Silabus"
    href="/silabus.pdf"
>
    Lihat Silabus
</TrackedCTA>
```

Gunakan `link` ketika klik menunjukkan minat tetapi bukan lead atau checkout.

## Form lead

Field standar adalah `name`, `email`, dan `phone`. `name` serta `phone` wajib; `email` opsional. Field lain otomatis masuk ke kolom JSON `extra`.

```tsx
import { useState } from 'react';
import { TrackedForm } from '@/components/tracking/TrackedForm';
import type { LeadResponse } from '@/components/tracking/TrackedForm';

export function RegistrationForm() {
    const [error, setError] = useState('');
    const success = (response: LeadResponse) => {
        window.location.assign(response.redirect_url);
    };

    return (
        <TrackedForm
            formName="main"
            onSuccess={success}
            onError={setError}
        >
            <input name="name" required />
            <input name="email" type="email" />
            <input name="phone" required />
            <input name="city" />
            {error && <p role="alert">{error}</p>}
            <button type="submit">Kirim</button>
        </TrackedForm>
    );
}
```

Alurnya:

1. Input pertama menghasilkan `form_start` satu kali untuk `formName` tersebut.
2. Submit dikirim ke `/lead`.
3. Server memvalidasi dan menyimpan data.
4. Server menulis event `lead`.
5. Browser menerima `redirect_url`.
6. Mode `none` menuju halaman terima kasih, `external` menuju URL payment klien, dan `internal` otomatis membuat checkout Duitku.

`onSuccess` digunakan pada mode `none` dan `external`. Pada mode `internal`, `TrackedForm` otomatis meminta invoice lalu membuka payment URL.

## Section tracking

```tsx
<section id="hero">...</section>
<section id="benefits">...</section>
<section id="pricing">...</section>
<section id="faq">...</section>
```

Gunakan ID singkat, unik, dan stabil. Jangan menggunakan teks heading sebagai ID jika teks sering berubah. Hook analytics menemukan section baru, termasuk section yang muncul setelah initial render.

`TrackedSection` hanya diperlukan jika struktur tidak dapat menggunakan elemen `<section>` asli.

## Event manual

Gunakan hanya ketika komponen tidak dapat memakai `TrackedCTA`:

```tsx
import { EVENT_TYPES, useAnalytics } from '@/hooks/use-analytics';

export function CustomButton() {
    const { track } = useAnalytics();

    return (
        <button
            onClick={() =>
                track(EVENT_TYPES.intent, {
                    zone: 'faq',
                    action: 'link',
                    cta_label: 'Lihat Silabus',
                })
            }
        >
            Lihat Silabus
        </button>
    );
}
```

Gunakan konstanta `EVENT_TYPES`; jangan menulis string event manual.

## Cara pengiriman event

- Event non-kritis masuk queue browser dan dikirim setiap dua detik atau saat batch mencapai sepuluh event.
- Event browser untuk WhatsApp lead dan direct checkout dikirim segera. Pada alur FORM, event `lead` dicatat oleh server setelah data form berhasil disimpan sehingga tidak bergantung pada queue browser.
- Saat halaman ditutup atau tab disembunyikan, queue menggunakan beacon agar navigasi tidak tertahan.
- Pengiriman yang gagal dicoba sekali, lalu dilepas agar UX pengguna tidak terganggu.

## Aset gambar dan video

Semua media landing page disimpan di `resources/assets/` dan dipanggil lewat helper `asset()`, bukan dari `public/`. Vite yang mem-bundle, memberi hash, dan menulis ulang URL-nya saat build sehingga cache busting berjalan otomatis.

```tsx
import { asset } from '@/lib/assets';

<img src={asset('hero-consultant.png')} alt="Konsultan" />
```

Untuk `background-image`, pakai inline style karena kelas Tailwind tidak bisa memuat ekspresi JavaScript:

```tsx
<div
    className="[background-size:cover] [background-position:center]"
    style={{ backgroundImage: `url(${asset('unair.png')})` }}
/>
```

Nama file yang tidak ada di `resources/assets/` menghasilkan string kosong dan peringatan di console saat development, bukan build yang gagal.

## Checklist setelah wiring

- Setiap CTA memiliki zone, action, dan label yang benar.
- Tidak ada CTA conversion penting yang masih memakai `<a>` biasa.
- Setiap form menggunakan nama `formName` yang stabil.
- Semua input memiliki atribut `name`.
- Section penting mempunyai ID unik.
- Browser console tidak menampilkan error.
- Dashboard menerima visit, intent, dan outcome yang sesuai.
