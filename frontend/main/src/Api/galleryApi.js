import api from "./axios";

export function galleryImageUrl(photo) {
  return api.getUri({
    url: "gallery/" + photo.id + "/image",
    params: { v: photo.image_version },
  });
}

export async function getGalleryPage(page, signal) {
  const { data } = await api.get("gallery", { params: { page }, signal });
  return data;
}

export async function saveGalleryPhoto(id, { title, description, image }) {
  const form = new FormData();
  form.append("title", title.trim());
  form.append("description", description.trim());
  if (image) form.append("image", image);
  // PHP parses multipart uploads on POST, including Laravel's PATCH override.
  if (id) form.append("_method", "PATCH");
  await api.post(id ? "gallery/" + id : "gallery", form);
}

export async function deleteGalleryPhoto(id) {
  await api.delete("gallery/" + id);
}
