import { changePassword } from "../../Api/Trainee/settings";
import { useState } from "react";
import { toast } from "react-toastify";
import { useTranslation } from "react-i18next";
export default function SettingsHooks() {
    const { t } = useTranslation('trainee');

    const [loadingAction, setLoadingAction] = useState(false);

    const [showCurrentPassword, setShowCurrentPassword] =
        useState(false);
    const [showNewPassword, setShowNewPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] =
        useState(false);

    const [formData, setFormData] = useState({
        currentPassword: "",
        newPassword: "",
        confirmPassword: "",
    });

    const [errors, setErrors] = useState({});


    const handleSubmit = async () => {
        console.log('asdkfjalsdkfjal;skdjf;laskdjf')
        if (!validateForm()) return;
        try {
            setLoadingAction(true);
            const response = await changePassword({
                current_password: formData.currentPassword,
                new_password: formData.newPassword,
                new_password_confirmation: formData.confirmPassword
            });
            setFormData({
                currentPassword: "",
                newPassword: "",
                confirmPassword: "",
            });
            toast.success(response.message);

        } catch (error) {
            toast.error(error.message);
        }
        finally {
            setLoadingAction(false)

        }

    };
    const validateForm = () => {
        const nextErrors = {};

        if (!formData.currentPassword) {
            nextErrors.currentPassword = t(
                "settings.validation.currentRequired",
                { defaultValue: "Current password is required." }
            );
        }

        if (!formData.newPassword) {
            nextErrors.newPassword = t(
                "settings.validation.newRequired",
                { defaultValue: "New password is required." }
            );
        } else if (formData.newPassword.length < 8) {
            nextErrors.newPassword = t(
                "settings.validation.minLength",
                { defaultValue: "Password must contain at least 8 characters." }
            );
        } else if (formData.newPassword === formData.currentPassword) {
            nextErrors.newPassword = t(
                "settings.validation.samePassword",
                { defaultValue: "New password must differ from current password." }
            );
        }

        if (!formData.confirmPassword) {
            nextErrors.confirmPassword = t(
                "settings.validation.confirmRequired",
                { defaultValue: "Please confirm your new password." }
            );
        } else if (
            formData.newPassword !== formData.confirmPassword
        ) {
            nextErrors.confirmPassword = t(
                "settings.validation.passwordMismatch",
                { defaultValue: "Passwords do not match." }
            );
        }

        setErrors(nextErrors);
        return Object.keys(nextErrors).length === 0;
    };
    return {
        loadingAction,
        showCurrentPassword,
        showNewPassword,
        showConfirmPassword,
        setShowCurrentPassword,
        setShowNewPassword,
        setShowConfirmPassword,
        errors,
        formData,
        setFormData,
        setErrors,
        handleSubmit
    }
}