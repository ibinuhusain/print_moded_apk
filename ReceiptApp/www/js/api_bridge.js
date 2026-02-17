// API Bridge for Receipt Capture Application
class ApiBridge {
    constructor() {
        this.baseURL = 'https://your-api-endpoint.com'; // Replace with your actual API endpoint
        this.token = null;
    }

    // Set authentication token
    setToken(token) {
        this.token = token;
    }

    // Submit receipt data to server
    async submitReceipt(receiptData) {
        try {
            const response = await fetch(`${this.baseURL}/api/receipts`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.token}`
                },
                body: JSON.stringify(receiptData)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            console.log('Receipt submitted successfully:', result);
            return result;
        } catch (error) {
            console.error('Error submitting receipt:', error);
            throw error;
        }
    }

    // Get receipt processing status
    async getReceiptStatus(receiptId) {
        try {
            const response = await fetch(`${this.baseURL}/api/receipts/${receiptId}/status`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.token}`
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            return result;
        } catch (error) {
            console.error('Error getting receipt status:', error);
            throw error;
        }
    }

    // Upload image file to server
    async uploadImage(fileURI, fileName) {
        return new Promise((resolve, reject) => {
            // Convert file URI to FileEntry
            window.resolveLocalFileSystemURL(fileURI, (fileEntry) => {
                fileEntry.file((file) => {
                    const reader = new FileReader();
                    
                    reader.onloadend = function() {
                        const blob = new Blob([new Uint8Array(this.result)], { type: file.type });
                        
                        const formData = new FormData();
                        formData.append('file', blob, fileName);
                        
                        fetch(`${this.baseURL}/api/upload`, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'Authorization': `Bearer ${this.token}`
                            }
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            resolve(data);
                        })
                        .catch(error => {
                            reject(error);
                        });
                    };
                    
                    reader.onerror = function(error) {
                        reject(error);
                    };
                    
                    reader.readAsArrayBuffer(file);
                }, (error) => {
                    reject(error);
                });
            }, (error) => {
                reject(error);
            });
        });
    }
}

// Global API Bridge instance
const apiBridge = new ApiBridge();