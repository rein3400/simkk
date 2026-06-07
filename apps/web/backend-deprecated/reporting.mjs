import ExcelJS from "exceljs";
import PDFDocument from "pdfkit";

export async function createFinancePdf(report) {
  return await new Promise((resolve) => {
    const doc = new PDFDocument({ margin: 48, size: "A4" });
    const chunks = [];
    doc.on("data", (chunk) => chunks.push(chunk));
    doc.on("end", () => resolve(Buffer.concat(chunks)));

    doc.fontSize(16).text("KLINIK KECANTIKAN SIM-KK", { align: "center" });
    doc.fontSize(10).text("Jl. Operasional Klinik No. 25, Samarinda", { align: "center" });
    doc.moveDown();
    doc.fontSize(14).text(report.title, { align: "center" });
    doc.fontSize(10).text(`Periode: ${report.period}`, { align: "center" });
    doc.moveDown();

    const startX = 48;
    let y = doc.y + 8;
    doc.fontSize(10).text("ID Transaksi", startX, y);
    doc.text("Debit", 190, y);
    doc.text("Kredit", 300, y);
    doc.text("Saldo", 410, y);
    y += 18;
    doc.moveTo(startX, y - 4).lineTo(545, y - 4).stroke();

    for (const row of report.rows) {
      doc.text(String(row.id), startX, y);
      doc.text(String(row.debit), 190, y);
      doc.text(String(row.kredit), 300, y);
      doc.text(String(row.saldo), 410, y);
      y += 18;
    }

    doc.moveDown(4);
    doc.text("Mengetahui,", 390, y + 28);
    doc.moveTo(390, y + 96).lineTo(520, y + 96).stroke();
    doc.text("Manajer Klinik", 410, y + 102);
    doc.end();
  });
}

export async function createWorkbook(report) {
  const workbook = new ExcelJS.Workbook();
  workbook.creator = "SIM-KK";
  workbook.created = new Date();
  const sheet = workbook.addWorksheet(report.title);
  const columns = Object.keys(report.rows[0] ?? { data: "" });
  sheet.addRow([report.title]);
  sheet.addRow([`Periode: ${report.period}`]);
  sheet.addRow([]);
  sheet.addRow(columns);
  for (const row of report.rows) {
    sheet.addRow(columns.map((key) => row[key]));
  }
  sheet.getRow(1).font = { bold: true, size: 14 };
  sheet.getRow(4).font = { bold: true };
  sheet.columns.forEach((column) => {
    column.width = 22;
  });
  return Buffer.from(await workbook.xlsx.writeBuffer());
}
