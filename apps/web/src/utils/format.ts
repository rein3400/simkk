export const rupiah = (value: number) => new Intl.NumberFormat("id-ID", {
  style: "currency",
  currency: "IDR",
  maximumFractionDigits: 0,
}).format(value);

export const percent = (value: number, fractionDigits = 1) =>
  `${value.toLocaleString("id-ID", { minimumFractionDigits: fractionDigits, maximumFractionDigits: fractionDigits })}%`;

const MONTHS_ID = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];

/** Parse "YYYY-MM-DD" or ISO into "DD MMM YYYY" (Indonesian). */
export const dateId = (input: string | Date | null | undefined): string => {
  if (!input) return "—";
  const value = typeof input === "string" ? input : input.toISOString();
  const [y, m, d] = value.slice(0, 10).split("-").map((part) => Number(part));
  if (!y || !m || !d) return value;
  return `${String(d).padStart(2, "0")} ${MONTHS_ID[m - 1]} ${y}`;
};

/** Format Date or string to "YYYY-MM-DD" suitable for <input type="date">. */
export const isoDate = (input: string | Date | null | undefined): string => {
  if (!input) return "";
  const value = typeof input === "string" ? input : input.toISOString();
  return value.slice(0, 10);
};

/** Pretty time from "YYYY-MM-DD HH:MM:SS" → "DD MMM YYYY · HH:MM". */
export const dateTimeId = (input: string | null | undefined): string => {
  if (!input) return "—";
  const datePart = input.slice(0, 10);
  const timePart = input.slice(11, 16);
  if (!timePart) return dateId(datePart);
  return `${dateId(datePart)} · ${timePart}`;
};

export const todayIso = (): string => {
  const now = new Date();
  const y = now.getFullYear();
  const m = String(now.getMonth() + 1).padStart(2, "0");
  const d = String(now.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
};

export const shortDay = (input: string | Date | null | undefined): string => {
  if (!input) return "";
  const value = typeof input === "string" ? input : input.toISOString();
  const d = Number(value.slice(8, 10));
  const m = Number(value.slice(5, 7));
  if (!d || !m) return value;
  return `${String(d).padStart(2, "0")}/${MONTHS_ID[m - 1] ?? m}`;
};
