import { scryptSync } from "node:crypto";

export const hashPassword = (password) => scryptSync(password, "simkk-local-salt", 32).toString("hex");

export const seed = {
  users: [
    { id: 1, username: "kasir", password: "simkk-2026", name: "Nadia Putri", role: "Kasir", shift: "Pagi" },
    { id: 2, username: "terapis", password: "simkk-2026", name: "dr. Melati", role: "Terapis", shift: "Treatment A" },
    { id: 3, username: "gudang", password: "simkk-2026", name: "Raka Pramana", role: "Gudang", shift: "Gudang" },
    { id: 4, username: "manajer", password: "simkk-2026", name: "Mira Santoso", role: "Manajer", shift: "Audit" },
  ],
  patients: [
    { id: 1, name: "Alya Maharani", age: 29, phone: "0812-4400-1188", recordId: "RM-2026-0018", concern: "Bekas jerawat dan tekstur kulit", lastVisit: "25 Mei 2026", riskNote: "Sensitif terhadap retinol tinggi" },
    { id: 2, name: "Dewi Lestari", age: 34, phone: "0821-8890-7712", recordId: "RM-2026-0021", concern: "Melasma ringan", lastVisit: "24 Mei 2026", riskNote: "Wajib sunscreen pasca tindakan" },
    { id: 3, name: "Yuni Kartika", age: 26, phone: "0813-5500-9821", recordId: "RM-2026-0025", concern: "Komedo area T-zone", lastVisit: "22 Mei 2026", riskNote: "Tidak ada alergi aktif" },
    { id: 4, name: "Bella Anggraini", age: 31, phone: "0852-7710-4410", recordId: "RM-2026-0029", concern: "Kulit kusam", lastVisit: "20 Mei 2026", riskNote: "Hindari scrub 3 hari" },
    { id: 5, name: "Sarah Amalia", age: 37, phone: "0811-6200-3498", recordId: "RM-2026-0030", concern: "Fine lines", lastVisit: "19 Mei 2026", riskNote: "Konsultasi dokter sebelum needle" },
    { id: 6, name: "Citra Ananda", age: 23, phone: "0822-1200-6811", recordId: "RM-2026-0034", concern: "Hydration maintenance", lastVisit: "18 Mei 2026", riskNote: "Aman untuk hydrating facial" },
  ],
  treatments: [
    { patientId: 1, date: "18 Mei", therapist: "Sinta", title: "Acne Calm Facial", notes: "Kemerahan turun, lanjut barrier repair." },
    { patientId: 1, date: "25 Mei", therapist: "Rani", title: "Bright Peel Mild", notes: "Patch test aman, foto after tersimpan." },
    { patientId: 2, date: "24 Mei", therapist: "Sinta", title: "Glow Infusion", notes: "Pigment spot dipantau 14 hari." },
  ],
  photos: [
    { patientId: 1, label: "Before", date: "18 Mei", objectRef: "local://clinical/RM-2026-0018/before-1805.jpg" },
    { patientId: 1, label: "After", date: "25 Mei", objectRef: "local://clinical/RM-2026-0018/after-2505.jpg" },
    { patientId: 2, label: "Before", date: "24 Mei", objectRef: "local://clinical/RM-2026-0021/before-2405.jpg" },
    { patientId: 2, label: "After", date: "24 Mei", objectRef: "local://clinical/RM-2026-0021/after-2405.jpg" },
  ],
  services: [
    { id: 1, name: "Acne Calm Facial", category: "Treatment", duration: "55m", price: 285000, commissionRate: 0.12 },
    { id: 2, name: "Bright Peel Mild", category: "Treatment", duration: "40m", price: 360000, commissionRate: 0.14 },
    { id: 3, name: "Glow Infusion", category: "Treatment", duration: "70m", price: 520000, commissionRate: 0.16 },
    { id: 4, name: "Hydra Cleanse", category: "Treatment", duration: "45m", price: 310000, commissionRate: 0.12 },
    { id: 5, name: "LED Recovery", category: "Treatment", duration: "25m", price: 180000, commissionRate: 0.1 },
    { id: 6, name: "Barrier Serum", category: "Produk", duration: "Retail", price: 215000, commissionRate: 0.05, stockProductId: 1, stockImpact: "-1 botol" },
    { id: 7, name: "Daily Sunscreen SPF50", category: "Produk", duration: "Retail", price: 175000, commissionRate: 0.04, stockProductId: 2, stockImpact: "-1 tube" },
    { id: 8, name: "Calming Toner", category: "Produk", duration: "Retail", price: 145000, commissionRate: 0.04, stockProductId: 3, stockImpact: "-1 botol" },
    { id: 9, name: "Paket Acne 3x", category: "Paket", duration: "3 sesi", price: 760000, commissionRate: 0.11 },
    { id: 10, name: "Paket Bridal Glow", category: "Paket", duration: "4 sesi", price: 1450000, commissionRate: 0.13 },
  ],
  therapists: [
    { id: 1, name: "Sinta Ayu", specialty: "Acne care", status: "Tersedia" },
    { id: 2, name: "Rani Wulandari", specialty: "Brightening", status: "Tersedia" },
    { id: 3, name: "Maya Cahyani", specialty: "Recovery", status: "Treatment" },
    { id: 4, name: "Lina Paramitha", specialty: "Hydration", status: "Istirahat" },
  ],
  transactions: [
    { id: "TRX-2505-031", patientId: 1, therapistId: 2, status: "Lunas", total: 575000, commission: 57100, time: "10:18" },
    { id: "TRX-2505-032", patientId: 2, therapistId: 1, status: "Menunggu", total: 520000, commission: 83200, time: "11:05" },
    { id: "TRX-2505-033", patientId: 3, therapistId: null, status: "Draft", total: 0, commission: 0, time: "11:20" },
  ],
  inventoryProducts: [
    { id: 1, name: "Barrier Serum", category: "Skincare" },
    { id: 2, name: "Daily Sunscreen SPF50", category: "Skincare" },
    { id: 3, name: "Calming Toner", category: "Skincare" },
    { id: 4, name: "Peeling Solution Mild", category: "Treatment" },
    { id: 5, name: "LED Eye Shield", category: "Alat" },
    { id: 6, name: "Hydra Ampoule", category: "Treatment" },
    { id: 7, name: "Acne Mask Sachet", category: "Treatment" },
    { id: 8, name: "Sterile Gauze", category: "Consumable" },
  ],
  batches: [
    { productId: 1, code: "BS-0426-A", qty: 12, hpp: 98000, expiry: "2026-09-12", supplier: "PT Dermalab" },
    { productId: 1, code: "BS-0526-B", qty: 22, hpp: 102000, expiry: "2027-01-20", supplier: "PT Dermalab" },
    { productId: 2, code: "DS-1225-X", qty: 5, hpp: 72000, expiry: "2026-06-30", supplier: "CV Sunmed" },
    { productId: 2, code: "DS-0526-Y", qty: 13, hpp: 75000, expiry: "2027-02-12", supplier: "CV Sunmed" },
    { productId: 3, code: "CT-0326-C", qty: 9, hpp: 61000, expiry: "2026-11-01", supplier: "Beauty Core" },
    { productId: 4, code: "PM-0226-A", qty: 6, hpp: 135000, expiry: "2026-08-01", supplier: "Aesthetic Pro" },
    { productId: 4, code: "PM-0526-B", qty: 8, hpp: 136500, expiry: "2027-03-15", supplier: "Aesthetic Pro" },
    { productId: 5, code: "LED-0126", qty: 42, hpp: 21000, expiry: "Reusable", supplier: "Medlite" },
    { productId: 6, code: "HA-0426", qty: 11, hpp: 88000, expiry: "2026-10-22", supplier: "Beauty Core" },
    { productId: 7, code: "AM-0526", qty: 57, hpp: 18500, expiry: "2027-02-18", supplier: "PT Dermalab" },
    { productId: 8, code: "SG-0526", qty: 120, hpp: 2500, expiry: "2028-05-01", supplier: "Medlite" },
  ],
};
