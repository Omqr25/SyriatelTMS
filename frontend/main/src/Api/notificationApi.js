    
import api from "./axios";

export async function registerNotificationTokenApi(token) {
  return api.post("notification/device-tokens", {
    token,
    platform: "web",
  });
}




// export const getAllNotificationApi = async () => {
//   const response = await api.get("notification/myNotifications");
//   console.log(response.data)
//   return response.data;
// };