// ── FOLDER ID — eBook Submissions folder in Google Drive ──
var FOLDER_ID = "1191c9ESVNxe4KByHenFp3EjarUyO3BVG";

function doGet(e) {
  try {
    const action = e.parameter.action || "getBooks";

    // ── CLAIM A BOOK ──
    if (action === "claim") {
      const sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
      const rank  = String(e.parameter.rank);
      const name  = e.parameter.name;
      const rows  = sheet.getDataRange().getValues();

      for (let i = 0; i < rows.length; i++) {
        if (String(rows[i][0]) === rank) {
          if (!rows[i][5] || rows[i][5].toString().trim() === "") {
            sheet.getRange(i + 1, 6).setValue(name);
          }
          return respond({ success: true });
        }
      }
      return respond({ success: false, message: "Book not found" });
    }

    // ── DEFAULT: GET ALL BOOKS ──
    const sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
    const rows  = sheet.getDataRange().getValues();
    const books = [];

    const sampleRich = sheet.getRange(1, 2).getRichTextValue();
    const sampleLink = sampleRich ? (sampleRich.getLinkUrl() || "") : "";

    for (let i = 0; i < rows.length; i++) {
      const rank = rows[i][0];
      if (typeof rank === "number" && rank > 0) {
        const richText  = sheet.getRange(i + 1, 2).getRichTextValue();
        const titleLink = richText ? (richText.getLinkUrl() || "") : "";
        books.push({
          rank:        rank,
          title:       rows[i][1] || "",
          description: rows[i][2] || "",
          category:    rows[i][3] || "",
          pages:       rows[i][4] || "",
          claimedBy:   rows[i][5] || "",
          titleLink:   titleLink
        });
      }
    }

    return respond({ books: books, samplePdf: sampleLink });

  } catch (err) {
    return respond({ error: err.message });
  }
}

function doPost(e) {
  try {
    const data = JSON.parse(e.postData.contents);
    const action = data.action || "";

    // ── CLAIM A BOOK (from tracker.html) ──────────────────────────────────────
    if (action === "claim") {
      const sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
      const rank  = String(data.rank);
      const name  = data.name;
      const rows  = sheet.getDataRange().getValues();

      for (let i = 0; i < rows.length; i++) {
        if (String(rows[i][0]) === rank) {
          if (!rows[i][5] || rows[i][5].toString().trim() === "") {
            sheet.getRange(i + 1, 6).setValue(name);
            return respond({ success: true });
          } else {
            return respond({ success: false, message: "This book is already claimed by " + rows[i][5] });
          }
        }
      }
      return respond({ success: false, message: "Book not found" });
    }

    // ── DAILY SUBMISSION WITH FILE ────────────────────────────────────────────
    if (action === "submit") {
      const name    = data.name    || "";
      const phone   = data.phone   || "";
      const date    = data.date    || "";
      const notes   = data.notes   || "";
      const books   = data.books   || [];
      const files   = data.files   || []; // [{name, mimeType, base64}]

      const ss = SpreadsheetApp.getActiveSpreadsheet();

      // Get or create Daily Submissions sheet
      let subSheet = ss.getSheetByName("Daily Submissions");
      if (!subSheet) {
        subSheet = ss.insertSheet("Daily Submissions");
        const headers = [
          "Timestamp", "Date", "Writer Name", "Phone Number",
          "Total Books Submitted", "Book 1", "Book 2", "Book 3",
          "Book 4", "Book 5", "Book 6", "Book 7", "Book 8",
          "Book 9", "Book 10", "Files Uploaded", "Drive Links", "Notes"
        ];
        subSheet.getRange(1, 1, 1, headers.length).setValues([headers]);
        subSheet.getRange(1, 1, 1, headers.length)
          .setBackground("#0d0820")
          .setFontColor("#FFD93D")
          .setFontWeight("bold")
          .setFontSize(11);
        subSheet.setFrozenRows(1);
        subSheet.setColumnWidth(1, 160);
        subSheet.setColumnWidth(3, 180);
        subSheet.setColumnWidth(4, 160);
        subSheet.setColumnWidth(17, 300);
      }

      // Upload files to Google Drive
      const folder = DriveApp.getFolderById(FOLDER_ID);

      // Create a subfolder per writer per date
      const subFolderName = `${name} — ${date}`;
      let writerFolder;
      const existing = folder.getFoldersByName(subFolderName);
      if (existing.hasNext()) {
        writerFolder = existing.next();
      } else {
        writerFolder = folder.createFolder(subFolderName);
      }

      const driveLinks = [];
      for (const file of files) {
        if (!file.base64 || !file.name) continue;
        const decoded = Utilities.base64Decode(file.base64);
        const blob    = Utilities.newBlob(decoded, file.mimeType, file.name);
        const uploaded = writerFolder.createFile(blob);
        uploaded.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
        driveLinks.push(uploaded.getUrl());
      }

      // Build the sheet row
      const row = [
        new Date(),       // Timestamp
        date,             // Date
        name,             // Writer Name
        phone,            // Phone Number
        books.length      // Total Books
      ];

      // Book 1–10
      for (let i = 0; i < 10; i++) {
        row.push(books[i] || "");
      }

      row.push(files.length);               // Files Uploaded count
      row.push(driveLinks.join("\n") || ""); // Drive Links
      row.push(notes);                       // Notes

      subSheet.appendRow(row);
      subSheet.autoResizeColumns(1, row.length);

      return respond({
        success: true,
        message: "Submission saved with files!",
        booksCount: books.length,
        filesUploaded: driveLinks.length,
        folderLink: writerFolder.getUrl()
      });
    }

    return respond({ success: false, message: "Unknown action" });

  } catch (err) {
    return respond({ error: err.message });
  }
}

function respond(data) {
  return ContentService
    .createTextOutput(JSON.stringify(data))
    .setMimeType(ContentService.MimeType.JSON);
}
