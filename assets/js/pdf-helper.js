function downloadPDF(elementId, reportName) {
    const element = document.getElementById(elementId);
    if (!element) {
        if(window.uiAlert) window.uiAlert("Report content not found.");
        else alert("Report content not found.");
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
    
    html2pdf().set(opt).from(element).toPdf().get('pdf').then(function(pdf) {
        window.open(pdf.output('bloburl'), '_blank');
    });
}
