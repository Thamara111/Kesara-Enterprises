function downloadPDF(elementId, reportName) {
    const element = document.getElementById(elementId);
    if (!element) {
        alert("Report content not found.");
        return;
    }
    
    const date = new Date().toISOString().split('T')[0];
    const filename = `${date}_${reportName}.pdf`;

    const opt = {
        margin:       0.2,
        filename:     filename,
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, logging: false },
        jsPDF:        { unit: 'in', format: 'letter', orientation: 'landscape' }
    };

    // Temporarily adjust some styles for better PDF output if needed
    const originalClasses = element.className;
    
    html2pdf().set(opt).from(element).save();
}
