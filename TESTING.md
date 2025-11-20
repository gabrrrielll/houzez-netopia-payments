# Ghid de Testare - Achiziționare Abonament

## Cum să testezi achiziționarea unui abonament (membership package)

### Pasul 1: Verifică configurarea

1. **Activează modul Membership:**
   - Mergi la **WordPress Admin → Theme Options → Paid Submission**
   - Selectează **"Membership"** ca tip de submitere plătită
   - Salvează setările

2. **Configurează Netopia Payments:**
   - Mergi la **WordPress Admin → Netopia Payments → Settings**
   - Introdu API Key-ul tău Netopia
   - Introdu Signature ID (POS Signature)
   - Selectează **Sandbox** pentru testare sau **Live** pentru producție
   - Activează Netopia Payments (bifează "Enable Netopia Payments")
   - Salvează setările

### Pasul 2: Creează un abonament (dacă nu există deja)

1. Mergi la **WordPress Admin → Packages** (sau **Houzez → Packages**)
2. Creează un nou abonament sau editează unul existent
3. Setează:
   - **Titlu** (ex: "Basic Package")
   - **Preț** (ex: 10)
   - **Număr de proprietăți** permise
   - **Număr de proprietăți featured** permise
   - **Perioada de facturare** (ex: 1 lună)
   - **Vizibilitate**: Setează ca "Visible" pentru a apărea pe pagina de abonamente

### Pasul 3: Accesează pagina de abonamente

**Opțiunea 1: Prin Dashboard (dacă ești logat)**
- Mergi la **Dashboard → Membership** sau **Dashboard → Packages**
- Vei vedea lista cu toate abonamentele disponibile

**Opțiunea 2: Prin pagina dedicată**
- Creează o pagină nouă în WordPress
- Alege template-ul **"Packages"** (Template Name: Packages)
- Publică pagina
- Accesează URL-ul paginii (ex: `https://roomshare.ro/packages/`)

**Opțiunea 3: Direct prin URL**
- Dacă știi ID-ul unui abonament, poți accesa direct pagina de plată:
  ```
  https://roomshare.ro/payment/?selected_package=ID_ABONAMENT
  ```
  (Înlocuiește `ID_ABONAMENT` cu ID-ul real al abonamentului)

### Pasul 4: Selectează un abonament

1. Pe pagina de abonamente, vei vedea toate abonamentele disponibile
2. Apasă pe butonul **"Get Started"** sau **"Select"** de pe abonamentul dorit
3. Vei fi redirecționat către pagina de plată cu parametrul `selected_package` în URL

### Pasul 5: Completează plata cu Netopia Payments

1. Pe pagina de plată, vei vedea:
   - Detaliile abonamentului selectat
   - Opțiunile de plată disponibile (PayPal, Stripe, Bank Transfer, **Netopia Payments**)

2. **Selectează Netopia Payments:**
   - Bifează opțiunea **"Netopia Payments"**
   - Formularul pentru card va apărea automat

3. **Completează datele cardului:**
   - **Card Number**: Folosește un card de test (vezi mai jos)
   - **Exp Month**: Selectează o lună viitoare (ex: 12)
   - **Exp Year**: Selectează un an viitor (ex: 2028)
   - **CVV**: Orice 3 cifre (ex: 123)

4. **Apasă "Complete Membership"**
   - Dacă totul este configurat corect, plata va fi procesată
   - Dacă este necesară autentificare 3D Secure, vei fi redirecționat către pagina băncii

### Carduri de test pentru Sandbox

Când testezi în modul **Sandbox**, poți folosi orice număr de card valid:

- **Visa**: `4111 1111 1111 1111`
- **Mastercard**: `5555 5555 5555 4444`
- **Orice dată viitoare** pentru expirare (ex: 12/2028)
- **Orice CVV de 3 cifre** (ex: 123)

**Important:** Asigură-te că folosești credențiale Sandbox când Sandbox mode este activat!

### Verificare după plată

După o plată reușită:

1. **Verifică în Dashboard:**
   - Mergi la **Dashboard → Membership**
   - Ar trebui să vezi abonamentul activat
   - Verifică numărul de proprietăți disponibile

2. **Verifică în WordPress Admin:**
   - Mergi la **Users → All Users**
   - Editează utilizatorul care a făcut plata
   - Verifică câmpurile:
     - `package_id` - ar trebui să conțină ID-ul abonamentului
     - `package_activation` - ar trebui să fie setat

3. **Verifică Invoice-ul:**
   - Mergi la **Dashboard → Invoices** (dacă este disponibil)
   - Ar trebui să existe o factură pentru plata efectuată

### Depanare

**Dacă nu vezi pagina de abonamente:**
- Verifică că ai setat "Membership" ca tip de submitere plătită
- Verifică că există abonamente create și setate ca "Visible"
- Verifică că pagina folosește template-ul "Packages"

**Dacă nu vezi Netopia Payments ca opțiune:**
- Verifică că plugin-ul este activat
- Verifică că Netopia Payments este activat în setări
- Verifică că API Key și Signature sunt configurate corect

**Dacă apare eroarea "Authorization required":**
- Verifică că API Key-ul este corect
- Verifică că Signature ID este corect
- Asigură-te că folosești credențiale Sandbox când Sandbox mode este activat
- Asigură-te că folosești credențiale Live când Live mode este activat

### URL-uri utile pentru testare

- **Pagina de abonamente**: `https://roomshare.ro/packages/` (sau URL-ul paginii tale cu template Packages)
- **Pagina de plată directă**: `https://roomshare.ro/payment/?selected_package=ID`
- **Dashboard Membership**: `https://roomshare.ro/dashboard/?dashboard=membership`

### Note importante

- Pentru a testa, asigură-te că ești logat cu un cont de utilizator
- Abonamentele gratuite (preț 0) pot funcționa diferit
- Verifică că abonamentul are un preț mai mare decât 0 pentru a testa plata reală
- În modul Sandbox, nu se vor procesa plăți reale

