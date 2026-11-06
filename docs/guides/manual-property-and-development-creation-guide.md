# ERE Manual Property & Development Creation Guide
## Complete Step-by-Step Operator & Developer Manual

**Platform:** Ethiopian Real Estate Marketplace (ERE)  
**Version:** Phase 5 Milestone 2  
**Target Audience:** Property Owners, Certified Brokers, Commercial Landlords, Hospitality Hosts, Real Estate Developers, and Platform Administrators.

---

## 1. Overview & System Prerequisites

The Ethiopian Real Estate Marketplace (ERE) provides dedicated workflows for publishing six primary marketplace asset classes:
1. **Residential & Commercial Properties for Sale (BUY)**
2. **Long-Term Residential Rentals (RENT)**
3. **Commercial & Industrial Leases (LEASE)**
4. **Urban & Agricultural Land Plots (LAND)**
5. **Short-Term Vacation & Serviced Apartments (STAYS)**
6. **Masterplanned Off-Plan Developments & Building Units (DEVELOPMENTS)**

### Environment & Media Configuration Prerequisites
Before uploading high-resolution property photography or legal property deeds, verify that the local PHP environment supports multi-part file uploads:

- **PHP Configuration (`php.ini`):**
  - `upload_tmp_dir`: Must be set to a writable temporary directory (e.g., `C:\Users\<User>\AppData\Local\Temp` on Windows or `/tmp` on Linux). If left commented out on Windows, PHP defaults to `C:\Windows\Temp`, which standard non-root processes cannot write to, triggering `UPLOAD_ERR_NO_TMP_DIR` (error code 6).
  - `upload_max_filesize`: Recommended `32M` minimum (accommodates high-resolution 10MB+ camera images).
  - `post_max_size`: Recommended `128M` minimum (accommodates multi-image payloads up to 30 files).
  - `memory_limit`: Recommended `512M`.
  - `max_file_uploads`: `30` or higher.
- **Storage Symlink:**
  - Execute `php artisan storage:link` to ensure `public/storage` points to `storage/app/public`.

---

## 2. Authentication & User Roles

To access property and development creation workflows, authenticate using the Ethiopian Phone OTP gateway:

1. Navigate to **`http://localhost:8000/auth/login`**.
2. Enter your registered Ethiopian telephone number (e.g., `+251911234567` for Super Admin / Certified Developer, or any registered mobile number).
3. Enter the 6-digit verification code. (In local development/QA environments, the code is returned directly or defaults to `187680`).
4. Click **Verify & Authenticate**.

### Role Entitlements:
- **Individual Owners / Landlords:** Can post properties in BUY, RENT, LEASE, and LAND categories.
- **Hospitality Hosts:** Can configure Short-Term Stays with nightly rates and check-in policies.
- **Licensed Real Estate Developers:** Can register Masterplanned Development Projects and manage building unit inventory.
- **Platform Administrators:** Have global authority to create, publish, and moderate all property and project categories.

---

## 3. How to Post a Property (BUY, RENT, LEASE, LAND)

All individual listings are created through the unified 5-step property wizard at **`http://localhost:8000/listings/create`**.

### Step 1: Transaction Category & Classification
1. Navigate to **`http://localhost:8000/listings/create`** (or click the **"+ Post Property"** button in the top navigation bar).
2. **Transaction Intent:** Select one of the following:
   - **For Sale (BUY):** Outright ownership transfer.
   - **Long-Term Rental (RENT):** Monthly residential leases.
   - **Commercial Lease (LEASE):** Commercial offices, retail shops, or industrial warehouses.
   - **Land Plot (LAND):** Urban residential, commercial, or agricultural parcels.
3. **Property Classification:** Select the specific category:
   - *Residential:* Apartment / Condominium, Villa, Townhouse, Compound Home.
   - *Commercial:* Office Floor, Commercial Building, Retail Showroom, Warehouse.
   - *Land:* Urban Land Plot, Agricultural Land, Industrial Land.
4. Click **"Next Step: Location & Address &rarr;"**.

### Step 2: Location & Ethiopian Geocoding
1. **Administrative Location (8-Level Hierarchy):**
   - **Region / Chartered City:** Select *Addis Ababa*, *Oromia*, *Amhara*, *Sidama*, etc.
   - **City / Zone:** Select *Addis Ababa*, *Bishoftu*, *Hawassa*, etc.
   - **Sub-City / District:** Select *Bole*, *Kirkos*, *Yeka*, *Nifas Silk-Lafto*, etc.
   - **Woreda / Kebele:** Select specific administrative division.
2. **Physical Address:**
   - **Specific Address Line:** Enter landmark description (e.g., *Bole Rwanda, Behind Edna Mall, Next to Telebirr Center*).
   - **Postal / House Number:** (Optional) Enter municipal house number.
3. **Map Coordinates:**
   - Click directly on the interactive PostGIS Leaflet map or enter coordinates (e.g., Latitude `9.0100000`, Longitude `38.7800000`).
4. Click **"Next Step: Physical Specifications &rarr;"**.

### Step 3: Physical Specifications & Amenities
1. **Dimensions:**
   - **Total Built Area (m²):** Enter floor space in square meters (e.g., `120.00`).
   - **Bedrooms & Bathrooms:** Specify room counts (e.g., `2` bedrooms, `2` bathrooms).
   - **Floor Level:** Enter the floor number within the building.
2. **Property Description:**
   - **English Title:** Enter descriptive title (e.g., *Modern 2-Bedroom Apartment for Sale in Bole*).
   - **Amharic Title (አማርኛ):** (Optional) e.g., *ዘመናዊ ባለ 2 መኝታ አፓርታማ በቦሌ*.
   - **Detailed Description:** Describe finishing materials, standby generator, parking, water reserve, and neighborhood conveniences.
3. **Amenities & Infrastructure:**
   - Check applicable boxes: *Standby Generator, Backup Water Tank, 24/7 Security / CCTV, Elevator / Lift, Underground Parking, Fiber Internet*.
4. Click **"Next Step: Photos & Legal Deeds &rarr;"**.

### Step 4: Photo & Legal Document Uploads
> [!IMPORTANT]
> The upload form utilizes pre-upload MIME validation and multi-file processing. Ensure all files are under 32MB each.

1. **Property Photography:**
   - Click the file selector under **"High-Resolution Property Photography"**.
   - Select 1 to 30 image files (`.jpg`, `.jpeg`, `.png`, `.webp`).
   - Recommended: Select 5 clear images showcasing the exterior, living room, bedrooms, kitchen, and bathroom.
   - The UI automatically displays the count of selected images (e.g., *5 photo(s) selected*).
2. **Legal Ownership Documents & Title Deeds:**
   - Click the file selector under **"Proof of Ownership / Title Deed / Carte"**.
   - Select 1 or more PDF or image files (`.pdf`, `.jpg`, `.png`).
   - Upload verified documents such as the Landholding Title (*የይዞታ ማረጋገጫ ካርታ*), Construction Permit, or Sales Contract.
   - The UI confirms selection (e.g., *1 document(s) selected*).
3. Click **"Next Step: Financial Pricing Engine &rarr;"**.

### Step 5: Financial Pricing Engine & Moderation Submission
1. **Currency & Valuation:**
   - All transactions are calculated in Ethiopian Birr (**ETB**).
   - **Total Price (ETB):** Enter the asking price (e.g., `8,500,000` ETB).
   - The pricing engine dynamically displays the breakdown per square meter (e.g., `70,833.33 ETB / m²`).
2. **Negotiation Terms:**
   - Select whether the price is **Fixed** or **Negotiable**.
   - If negotiable, optionally specify the minimum acceptable offer threshold.
3. **Submit for Moderation:**
   - Click **"Submit Listing for Moderation"**.
   - The platform verifies files, computes SHA-256 cryptographic hashes for uploaded documents, creates audit records, and safely redirects you to your **Dashboard** with a success notification:  
     *“Your property listing has been submitted for moderation review.”*

---

## 4. How to Register a Short-Term Stay (STAYS)

To list a hospitality or vacation rental unit on **`http://localhost:8000/stays`**:

1. Follow Steps 1 through 4 above, selecting **"Short-Term Stay (STAYS)"** in Step 1.
2. In the Hospitality Pricing configuration:
   - **Base Nightly Rate (ETB):** e.g., `2,500.00` ETB/night.
   - **Weekend Rate:** Optional surcharge for Friday/Saturday.
   - **Cleaning Fee (ETB):** One-time sanitization fee.
   - **Security Deposit (ETB):** Refundable deposit held during occupancy.
3. Configure Hospitality Policies:
   - **Check-in Time:** (e.g., *After 14:00*).
   - **Check-out Time:** (e.g., *Before 11:00*).
   - **Maximum Guest Capacity:** Specify adults, children, and infants.
   - **Instant Booking:** Toggle whether reservations require host manual approval or confirm automatically upon payment.
4. Submit for publication. Active stays immediately appear on the interactive booking calendar and map at **`/stays`**.

---

## 5. How Developers Create Masterplanned Projects & Manage Units (DEVELOPMENTS)

Off-plan real estate developments require specialized structuring under Ethiopian Proclamation No. 1357/2024, connecting civil construction milestones, installment payment schedules, and individual unit inventory.

### Step 1: Accessing the Project Creation Portal
1. Authenticate with an account possessing a **Developer Profile** or **Super Administrator** credentials.
2. Navigate directly to **`http://localhost:8000/projects/create`** (or click **"+ Create New Project"** from the top of the Projects Directory at **`http://localhost:8000/projects`**).

### Step 2: Registering Project Details
1. **Section 1: Identification & Developer Entity:**
   - **Project Title (English):** e.g., `QA Manual Development Creation — Addis Test Residences`.
   - **Project Title (Amharic):** e.g., `አዲስ ቴስት ሬዚደንስ`.
   - **Developer Company:** Select your registered developer legal entity (e.g., *Bole Horizon Developments PLC*).
   - **Masterplan Description:** Describe the structural architecture, seismic code compliance, amenities, security, and residential community concept.
2. **Section 2: Classification, Permitting & Timeline:**
   - **Development Type:** Choose *Residential Complex*, *Mixed-Use Development*, *Commercial Tower*, or *Gated Villa Community*.
   - **Lifecycle Status:** Select *Planning Phase*, *Pre-Construction*, *Under Construction*, *Near Completion*, or *Completed*.
   - **Total Land Parcel Area (m²):** e.g., `4,000.00`.
   - **Building Permit Number:** Official municipal permit code (e.g., `AA/CON/BP-2026-00421`).
   - **Expected Delivery Date:** e.g., `2028-12-31`.
   - **Estimated Construction Progress (%):** e.g., `25.0%`.
3. **Section 3: Geographic Location & Coordinates:**
   - **City:** Select *Addis Ababa*.
   - **Sub-City:** Select *Bole*.
   - **Street Address:** e.g., *Bole Rwanda, Behind Edna Mall, Addis Ababa*.
   - **Coordinates:** Latitude `9.0100000`, Longitude `38.7800000`.
4. Click **"Create Development Project &rarr;"**.
5. The system creates the project, initializes default buildings (*Main Tower*), establishes Floor 1, populates standard civil construction milestones (*Foundation*, *Structure*, *Masonry*, *Finishing*), and redirects to the **Developer Operations Management Console** at:  
   `http://localhost:8000/projects/<project-slug>/manage`.

### Step 3: Managing Unit Inventory
1. In the Project Management Console, click on tab **"5. Unit Inventory Management"**.
2. Locate the **"+ Add New Unit to Development"** form at the bottom of the tab.
3. Fill out the unit specifications:
   - **Unit Number:** e.g., `A-101` or `B-204`.
   - **Unit Type:** *Studio*, *1 Bedroom*, *2 Bedrooms*, *3 Bedrooms*, *4 Bedrooms*, *Penthouse*, or *Commercial / Retail*.
   - **Floor Number:** e.g., `1`.
   - **Total Net Area (m²):** e.g., `110.00`.
   - **Bedrooms / Bathrooms:** e.g., `2` bedrooms, `2` bathrooms.
   - **Total Sales Price (ETB):** e.g., `8,800,000.00` ETB (the system automatically calculates price per m²: `80,000.00 ETB / m²`).
   - **Orientation:** *North-East*, *South-West*, *North-West*, *South-East*.
   - **Availability Status:** Select *Available for Reservation*, *Reserved (Temporary Hold)*, *Contract Signed*, or *Sold*.
4. Click **"+ Add Unit to Inventory"**.
5. The unit is immediately saved, assigned a unique UUID, linked to the project's building floor, and displayed in the live Unit Inventory Matrix.

### Step 4: Verifying the Public Showcase
- Click the **"Public Showcase & Matrix"** button in the header (or navigate to `http://localhost:8000/projects/<project-slug>`).
- Prospective buyers and investors will see the masterplan overview, construction progress gauge, 3D building selector, and the new unit listed in the real-time availability table ready for formal purchase offers or reservations.

---

## 6. Verifying the Six Marketplace Destinations

All six destinations are permanently seeded and live at the following locations:

| Destination | Marketplace Section | Public URL | Verified Test / Demo Record |
|---|---|---|---|
| **1** | **BUY** | `http://localhost:8000/search?mode=BUY` | *Modern 2-Bedroom Apartment for Sale in Bole* (8,500,000 ETB) |
| **2** | **RENT** | `http://localhost:8000/search?mode=RENT` | *Furnished 2-Bedroom Apartment for Rent in Kazanchis* (35,000 ETB/mo) |
| **3** | **LEASE** | `http://localhost:8000/search?mode=LEASE` | *Modern Commercial Office Space for Lease in Bole* (180,000 ETB/mo) |
| **4** | **LAND** | `http://localhost:8000/search?category=land` | *Residential Land Plot for Sale in Summit* (6,500,000 ETB) |
| **5** | **STAYS** | `http://localhost:8000/stays` | *Furnished Short-Stay Studio Apartment in Bole* (2,500 ETB/night) |
| **6** | **DEVELOPMENTS** | `http://localhost:8000/projects/bole-tower-residences` | *Bole Horizon Luxury Residences* (Unit B-102: 9,200,000 ETB) |

---

## 7. Troubleshooting Common Operator Questions

### Q1: Why did photo uploads fail with *"The photos.0 failed to upload"*?
- **Cause:** In local PHP environments on Windows, `upload_tmp_dir` was unconfigured, causing PHP to default to `C:\Windows\Temp` which regular Windows users cannot write to, or file sizes exceeded the default `upload_max_filesize = 2M`.
- **Solution:** Configure `upload_tmp_dir = "C:\Users\<User>\AppData\Local\Temp"` and `upload_max_filesize = 32M` in your active `php.ini`. The ERE codebase now includes diagnostic detection that displays the exact operating system error code.

### Q2: Can a regular user create a Development Project?
- **Rule:** Under Ethiopian Proclamation No. 1357/2024, only entities verified with a certified Developer License (`developer` company or Super Administrator) may register off-plan developments. If a non-developer attempts to access `/projects/create`, the platform responds with HTTP 403 Forbidden.

### Q3: How do I moderate submitted listings?
- Platform administrators can log in and visit **`http://localhost:8000/admin/moderation`** to review pending listings, inspect uploaded title deeds, view the automated fraud-signal evaluation score, and approve or reject submissions.

