import zipfile
import os

def zip_plugin():
    src_dir = '/home/user/card/cartara-pro'
    zip_path = '/home/user/card/cartara-pro.zip'
    
    with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
        for root, dirs, files in os.walk(src_dir):
            for file in files:
                file_path = os.path.join(root, file)
                arcname = os.path.relpath(file_path, os.path.dirname(src_dir))
                zipf.write(file_path, arcname)
    print(f"Created {zip_path} successfully!")

if __name__ == '__main__':
    zip_plugin()
