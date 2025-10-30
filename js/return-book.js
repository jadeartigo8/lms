// js/return-book.js
document.addEventListener("DOMContentLoaded", function () {
    const dueDateInput = document.getElementById("dueDate").value;
    const returnStatus = document.getElementById("returnStatus").value;
    const fineInput = document.getElementById("fineInput");
    const remarksInput = document.getElementById("remarksInput");
    const remarksDisplay = document.getElementById("remarksDisplay");
    const noticeDiv = document.getElementById("fineNotice");

    if (returnStatus == "1") return; // Already returned

    const dueDate = new Date(dueDateInput);
    dueDate.setHours(23, 59, 59, 999); // End of day

    const now = new Date();
    // Don't reset — keep full timestamp
    now.setHours(0, 0, 0, 0); // Compare dates only
    dueDate.setHours(0, 0, 0, 0);

    let fine = 0;
let daysLate = 0;
let message = "";
let noticeClass = "";
let remarksText = "";

if (now < dueDate) {
    const msEarly = dueDate - now;
    const daysEarly = Math.ceil(msEarly / (1000 * 60 * 60 * 24));
    if (daysEarly > 1) {
        message = `<i class="fas fa-clock"></i> Returned <strong>${daysEarly} days</strong> early. No fine.`;
        remarksText = "Returned early";
    } else {
        message = `<i class="fas fa-calendar-check"></i> Due today. No fine if returned now.`;
        remarksText = "Returned on due date";
    }
    noticeClass = "notice early";
    fine = 0;
} else {
    // Overdue
    const msLate = now - dueDate;
    daysLate = Math.floor(msLate / (1000 * 60 * 60 * 24)) + 1; // +1 to count partial day
    fine = daysLate * 10;
    message = `<i class="fas fa-exclamation-circle"></i> <strong>${daysLate} day${daysLate > 1 ? 's' : ''}</strong> overdue. Fine: <strong>₱${fine}</strong>`;
    noticeClass = "notice overdue";
    remarksText = `Overdue by ${daysLate} day${daysLate > 1 ? 's' : ''}`;
}

fineInput.value = fine;
remarksInput.value = remarksText;
remarksDisplay.value = remarksText;
remarksDisplay.style.color = now < dueDate ? "#1565c0" : "#e65100";

if (message) {
    noticeDiv.innerHTML = `<div class="${noticeClass}">${message}</div>`;
}

    // Update UI
    fineInput.value = fine;
    if (message) {
        noticeDiv.innerHTML = `<div class="${noticeClass}">${message}</div>`;
    }
});