function downloadPDF(elementId, reportName) {
    let element = document.getElementById(elementId);
    
    // Fallbacks for known container ID mappings
    if (!element) {
        const fallbacks = {
            'orders-container': 'orders-list-container',
            'inventory-container': 'inv-list-container',
            'suppliers-container': 'suppliers-list-container',
            'customer-list-container': 'customer-list-container',
            'purchase-orders-list-container': 'purchase-orders-list-container'
        };
        const fbId = fallbacks[elementId];
        if (fbId) element = document.getElementById(fbId);
    }

    if (!element) {
        if (window.uiAlert) window.uiAlert("Report content not found.");
        else alert("Report content not found.");
        return;
    }

    // Clone element to sanitize for printing
    const clone = element.cloneNode(true);

    // Convert all canvas elements (e.g. Chart.js charts) in clone to <img> elements with base64 PNG data
    const origCanvases = element.querySelectorAll('canvas');
    const cloneCanvases = clone.querySelectorAll('canvas');
    origCanvases.forEach((origCanvas, idx) => {
        if (cloneCanvases[idx]) {
            try {
                const imgData = origCanvas.toDataURL('image/png');
                const img = document.createElement('img');
                img.src = imgData;
                img.style.maxWidth = '100%';
                img.style.maxHeight = '350px';
                img.style.display = 'block';
                img.style.margin = '0 auto';
                cloneCanvases[idx].parentNode.replaceChild(img, cloneCanvases[idx]);
            } catch(e) {
                console.error("Error converting canvas to image for PDF:", e);
            }
        }
    });

    // Remove interactive/non-printable elements from clone
    const removeSelectors = [
        'button', 'input', 'select', 'form', '.print\\:hidden', '.no-print',
        '.action-btn', 'i.ti-search', 'i.ti-filter', 'i.ti-dots-vertical',
        'a[href^="javascript"]', '.sr-only', '.chip'
    ];
    removeSelectors.forEach(sel => {
        clone.querySelectorAll(sel).forEach(el => el.remove());
    });

    // If report is an analytics/dashboard report, export the full layout; otherwise if it's a dedicated single table wrapper, export outerHTML.
    const isReportTab = elementId.startsWith('tab-') || reportName.includes('Report');
    const table = clone.querySelector('table');
    const contentHtml = (!isReportTab && table && clone.children.length === 1) ? table.outerHTML : clone.innerHTML;

    // Format display title and reference code
    const titleMap = {
        'Orders_List': 'Wholesale Orders Report',
        'Invoice': 'Tax Invoice',
        'Customers_List': 'Wholesale Customer Directory',
        'Inventory_Report': 'Inventory & Stock Report',
        'Suppliers_List': 'Supplier Directory',
        'Purchase_Orders_List': 'Purchase Orders Log',
        'Purchase_Order': 'Purchase Order',
        'Delivery_Personnel_Report': 'Delivery Personnel Roster',
        'Delivery_Assignments_Report': 'Delivery Assignments Report',
        'Audit_Trail_Report': 'System Audit Trail Report',
        'Sales_Report': 'Sales Performance Report',
        'Products_Report': 'Products & Category Analytics',
        'Customers_Report': 'Customer Activity Analytics'
    };

    const cleanTitle = titleMap[reportName] || reportName.replace(/_/g, ' ').toUpperCase();
    const prefix = reportName.substring(0, 3).toUpperCase();
    const refCode = `${prefix}-${Date.now().toString().slice(-6)}`;
    const dateStr = new Date().toLocaleDateString();

    const printWindow = window.open('', '_blank');
    if (!printWindow) {
        alert("Pop-up blocked. Please allow pop-ups for this site to print.");
        return;
    }

    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${cleanTitle} - Kesara Enterprises</title>
            <style>
                * { box-sizing: border-box; }
                body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #0f172a; padding: 40px; background: #fff; line-height: 1.5; font-size: 13px; }
                .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0F6E56; padding-bottom: 20px; margin-bottom: 24px; }
                .logo { font-size: 24px; font-weight: 800; color: #0F6E56; letter-spacing: -0.5px; }
                .sublogo { font-size: 12px; color: #64748b; margin-top: 2px; }
                .doc-title { text-align: right; }
                .doc-title h2 { font-size: 18px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0; text-transform: uppercase; letter-spacing: 0.5px; }
                .doc-meta { font-size: 12px; color: #64748b; line-height: 1.4; }
                .content { margin-top: 10px; width: 100%; }
                
                /* Table Styles */
                table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; text-align: left; }
                th { background-color: #f8fafc !important; color: #475569 !important; font-weight: 700; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; padding: 10px 12px; border-bottom: 2px solid #cbd5e1; }
                td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; color: #334155; vertical-align: middle; }
                tr:nth-child(even) td { background-color: #fafafa; }
                
                /* Text alignment & weight utilities */
                .text-right { text-align: right !important; }
                .text-center { text-align: center !important; }
                .text-left { text-align: left !important; }
                .font-bold, .font-semibold, .font-black { font-weight: 700 !important; }
                .uppercase { text-transform: uppercase !important; }

                /* Status & Badge utilities */
                span[class*="bg-"], div[class*="bg-"] { display: inline-block; padding: 3px 8px; border-radius: 9999px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
                .bg-emerald-50, .bg-emerald-100 { background-color: #ecfdf5 !important; color: #047857 !important; border: 1px solid #a7f3d0 !important; }
                .bg-blue-50, .bg-blue-100 { background-color: #eff6ff !important; color: #1d4ed8 !important; border: 1px solid #bfdbfe !important; }
                .bg-amber-50, .bg-amber-100 { background-color: #fffbeb !important; color: #b45309 !important; border: 1px solid #fde68a !important; }
                .bg-red-50, .bg-red-100 { background-color: #fef2f2 !important; color: #b91c1c !important; border: 1px solid #fecaca !important; }
                .bg-purple-50, .bg-purple-100 { background-color: #faf5ff !important; color: #6b21a8 !important; border: 1px solid #e9d5ff !important; }
                .bg-gray-50, .bg-gray-100 { background-color: #f8fafc !important; color: #475569 !important; border: 1px solid #e2e8f0 !important; }

                .totals { margin-top: 30px; text-align: right; font-size: 16px; font-weight: 700; color: #0F6E56; }
                .footer { margin-top: 40px; font-size: 11px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 20px; }
                
                @media print {
                    body { padding: 20px; }
                    button, .no-print { display: none !important; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div>
                    <div class="logo">Kesara Enterprises</div>
                    <div class="sublogo">Wholesale Underwear Supplier Sri Lanka</div>
                    <div class="sublogo">Colombo, Sri Lanka</div>
                </div>
                <div class="doc-title">
                    <h2>${cleanTitle}</h2>
                    <div class="doc-meta">Date: ${dateStr}</div>
                    <div class="doc-meta">Reference: ${refCode}</div>
                </div>
            </div>
            <div class="content">
                ${contentHtml}
            </div>
            <div class="footer">
                <p>This is an official administrative document generated from Kesara Enterprises Management Portal.</p>
                <p>© ${new Date().getFullYear()} Kesara Enterprises. All rights reserved.</p>
            </div>
            <script>
                window.onload = function() {
                    window.print();
                }
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

