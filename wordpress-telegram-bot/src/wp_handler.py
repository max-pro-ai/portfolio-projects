"""
WordPress API Handler Module
Handles all interactions with the WordPress REST API
"""

import base64
import aiohttp
from typing import Dict, Any, Optional, List

class WordPressHandler:
    def __init__(self, wp_url: str, username: str, app_password: str):
        """
        Initialize WordPress handler with credentials.
        
        Args:
            wp_url: WordPress site URL
            username: WordPress username
            app_password: WordPress application password
        """
        self.wp_url = wp_url.rstrip('/')
        self.api_url = f"{self.wp_url}/wp-json/wp/v2"
        self.auth = base64.b64encode(
            f"{username}:{app_password}".encode()
        ).decode()

    async def upload_image(self, image_data: bytes, filename: str) -> Optional[Dict[str, Any]]:
        """
        Upload an image to WordPress.
        
        Args:
            image_data: Raw image bytes
            filename: Name for the uploaded file
            
        Returns:
            Dictionary containing media details if successful, None otherwise
        """
        try:
            headers = {
                'Authorization': f'Basic {self.auth}',
                'Content-Type': 'image/jpeg',
                'Content-Disposition': f'attachment; filename="{filename}"'
            }
            
            async with aiohttp.ClientSession() as session:
                async with session.post(
                    f"{self.api_url}/media",
                    data=image_data,
                    headers=headers
                ) as resp:
                    if resp.status == 201:
                        return await resp.json()
                    else:
                        print(f"❌ Media upload failed! Status: {resp.status}")
                        print(f"Response: {await resp.text()}")
                        return None
        except Exception as e:
            print(f"❌ Exception in upload_image: {e}")
            return None

    def _prepare_content(self, content: str, media_info: List[Dict[str, Any]]) -> str:
        """
        Prepare post content with media.
        
        Args:
            content: Original post content
            media_info: List of uploaded media information
            
        Returns:
            Formatted content string with media
        """
        if not media_info:
            return content

        # Add featured image at the top for single image
        if len(media_info) == 1:
            image_url = media_info[0].get('source_url')
            if image_url:
                return f'<img src="{image_url}" alt="" />\n\n{content}'
        
        # Add gallery shortcode for multiple images
        gallery_ids = ','.join(str(media['id']) for media in media_info if media.get('id'))
        if gallery_ids:
            return f"{content}\n\n[gallery ids=\"{gallery_ids}\"]"
            
        return content

    async def create_post(
        self,
        title: str,
        content: str,
        slug: str,
        media_info: List[Dict[str, Any]]
    ) -> Optional[Dict[str, Any]]:
        """
        Create a new WordPress post.
        
        Args:
            title: Post title
            content: Post content
            slug: URL slug
            media_info: List of uploaded media information
            
        Returns:
            Post data if successful, None otherwise
        """
        try:
            final_content = self._prepare_content(content, media_info)
            featured_media_id = media_info[0]['id'] if media_info else None
            
            post_data = {
                'title': title,
                'content': final_content,
                'status': 'publish',
                'slug': slug
            }
            if featured_media_id:
                post_data['featured_media'] = featured_media_id
            
            headers = {
                'Authorization': f'Basic {self.auth}',
                'Content-Type': 'application/json'
            }
            
            async with aiohttp.ClientSession() as session:
                async with session.post(
                    f"{self.api_url}/posts",
                    json=post_data,
                    headers=headers
                ) as resp:
                    if resp.status == 201:
                        return await resp.json()
                    else:
                        print(f"❌ Post creation failed! Status: {resp.status}")
                        print(f"Response: {await resp.text()}")
                        return None
        except Exception as e:
            print(f"❌ Exception in create_post: {e}")
            return None
