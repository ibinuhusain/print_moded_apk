from PIL import Image
import os

def create_icons():
    # Open the base image (assuming we have a larger version or the 512x512 version)
    base_image_path = '/workspace/www/images/icon-512x512.png'
    base_img = Image.open(base_image_path)
    
    # Define the sizes we need for the PWA
    sizes = [72, 96, 128, 144, 152, 192, 384, 512]
    
    # Create directory if it doesn't exist
    icons_dir = '/workspace/www/images/'
    os.makedirs(icons_dir, exist_ok=True)
    
    # Generate each size
    for size in sizes:
        # Resize the image
        resized_img = base_img.resize((size, size), Image.LANCZOS)
        
        # Save the resized image
        output_path = os.path.join(icons_dir, f'icon-{size}x{size}.png')
        resized_img.save(output_path, 'PNG')
        print(f'Created icon: {output_path}')
    
    print('All icons created successfully!')

if __name__ == '__main__':
    create_icons()