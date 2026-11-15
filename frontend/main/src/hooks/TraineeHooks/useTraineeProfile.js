import { useEffect, useState } from "react";
import { getTraineeProfile } from "../../../../Api/Trainee/profileApi.js";

export default function useTraineeProfile() {

    const [profile, setProfile] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {

        const fetchProfile = async () => {
            try {
                const response = await getTraineeProfile();

                console.log("Profile response:", response.data);

                // Adjust this after inspecting the actual response.
                const profileData = response.data.data ?? response.data;

                setProfile(profileData);
            }
            catch (err) {
                setError(
                    err.response?.data?.message ??
                    err.message ??
                    "Failed to fetch profile"
                );
            }
            finally {
                setLoading(false);
            }
        };

        fetchProfile();

    }, []);

    return { profile, loading, error };
}