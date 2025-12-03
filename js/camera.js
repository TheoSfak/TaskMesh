// Camera Integration Module for TaskMesh PWA

class CameraHelper {
    constructor() {
        this.stream = null;
        this.video = null;
        this.canvas = null;
    }

    // Check if camera is available
    static isAvailable() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    }

    // Open camera
    async openCamera(videoElement) {
        try {
            this.video = videoElement;
            
            const constraints = {
                video: {
                    facingMode: 'environment', // Use back camera on mobile
                    width: { ideal: 1920 },
                    height: { ideal: 1080 }
                }
            };

            this.stream = await navigator.mediaDevices.getUserMedia(constraints);
            this.video.srcObject = this.stream;
            
            return true;
        } catch (error) {
            console.error('[Camera] Failed to open camera:', error);
            throw new Error('Unable to access camera: ' + error.message);
        }
    }

    // Switch between front and back camera
    async switchCamera() {
        if (!this.stream) return;

        const videoTrack = this.stream.getVideoTracks()[0];
        const currentFacingMode = videoTrack.getSettings().facingMode;
        
        this.closeCamera();

        const constraints = {
            video: {
                facingMode: currentFacingMode === 'user' ? 'environment' : 'user',
                width: { ideal: 1920 },
                height: { ideal: 1080 }
            }
        };

        this.stream = await navigator.mediaDevices.getUserMedia(constraints);
        this.video.srcObject = this.stream;
    }

    // Capture photo from video stream
    capturePhoto(quality = 0.92) {
        if (!this.video || !this.video.videoWidth) {
            throw new Error('Video not ready');
        }

        // Create canvas with video dimensions
        this.canvas = document.createElement('canvas');
        this.canvas.width = this.video.videoWidth;
        this.canvas.height = this.video.videoHeight;

        // Draw current video frame to canvas
        const ctx = this.canvas.getContext('2d');
        ctx.drawImage(this.video, 0, 0, this.canvas.width, this.canvas.height);

        // Convert to blob
        return new Promise((resolve, reject) => {
            this.canvas.toBlob(
                (blob) => {
                    if (blob) {
                        resolve(blob);
                    } else {
                        reject(new Error('Failed to capture photo'));
                    }
                },
                'image/jpeg',
                quality
            );
        });
    }

    // Compress and resize image
    async compressImage(blob, maxWidth = 1200, maxHeight = 1200, quality = 0.85) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');

            img.onload = () => {
                // Calculate new dimensions
                let width = img.width;
                let height = img.height;

                if (width > height) {
                    if (width > maxWidth) {
                        height = height * (maxWidth / width);
                        width = maxWidth;
                    }
                } else {
                    if (height > maxHeight) {
                        width = width * (maxHeight / height);
                        height = maxHeight;
                    }
                }

                canvas.width = width;
                canvas.height = height;

                // Draw and compress
                ctx.drawImage(img, 0, 0, width, height);
                
                canvas.toBlob(
                    (blob) => {
                        if (blob) {
                            resolve(blob);
                        } else {
                            reject(new Error('Failed to compress image'));
                        }
                    },
                    'image/jpeg',
                    quality
                );
            };

            img.onerror = () => reject(new Error('Failed to load image'));
            img.src = URL.createObjectURL(blob);
        });
    }

    // Close camera and release resources
    closeCamera() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
        if (this.video) {
            this.video.srcObject = null;
        }
    }

    // Create camera modal UI
    static createCameraModal(onCapture, onClose) {
        const modal = document.createElement('div');
        modal.id = 'camera-modal';
        modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black';
        modal.innerHTML = `
            <div class="relative w-full h-full flex flex-col">
                <!-- Header -->
                <div class="absolute top-0 left-0 right-0 z-10 flex justify-between items-center p-4 bg-gradient-to-b from-black/50 to-transparent">
                    <button id="camera-close" class="text-white text-2xl hover:text-gray-300 transition">
                        <i class="fas fa-times"></i>
                    </button>
                    <button id="camera-switch" class="text-white text-2xl hover:text-gray-300 transition">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>

                <!-- Video preview -->
                <video id="camera-video" autoplay playsinline class="w-full h-full object-cover"></video>

                <!-- Controls -->
                <div class="absolute bottom-0 left-0 right-0 z-10 flex justify-center items-center p-8 bg-gradient-to-t from-black/50 to-transparent">
                    <button id="camera-capture" class="w-20 h-20 rounded-full bg-white border-4 border-gray-300 hover:bg-gray-100 transition transform hover:scale-105 shadow-2xl">
                        <i class="fas fa-camera text-2xl text-gray-700"></i>
                    </button>
                </div>

                <!-- Preview canvas (hidden) -->
                <canvas id="camera-preview" class="hidden"></canvas>
            </div>
        `;

        document.body.appendChild(modal);

        // Initialize camera
        const camera = new CameraHelper();
        const video = modal.querySelector('#camera-video');
        
        camera.openCamera(video).catch(error => {
            alert('Camera error: ' + error.message);
            onClose();
        });

        // Event listeners
        modal.querySelector('#camera-close').addEventListener('click', () => {
            camera.closeCamera();
            modal.remove();
            onClose();
        });

        modal.querySelector('#camera-switch').addEventListener('click', async () => {
            try {
                await camera.switchCamera();
            } catch (error) {
                console.error('[Camera] Switch failed:', error);
            }
        });

        modal.querySelector('#camera-capture').addEventListener('click', async () => {
            try {
                const photo = await camera.capturePhoto();
                const compressed = await camera.compressImage(photo, 1200, 1200, 0.85);
                
                camera.closeCamera();
                modal.remove();
                onCapture(compressed);
            } catch (error) {
                alert('Capture error: ' + error.message);
            }
        });

        return modal;
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CameraHelper;
}
