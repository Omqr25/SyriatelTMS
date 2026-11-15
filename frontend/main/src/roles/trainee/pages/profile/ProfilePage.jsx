import Image from "../../../../components/ui/Image.jsx";
import TitleField from "../../../../components/ui/Title_Field.jsx";
import TextField from "../../../../components/ui/Text_Field.jsx";
import BlocArea from "../../../../components/ui/Bloc_Area.jsx";
import Button from "../../../../components/ui/Button.jsx"
import styles from "./ProfilePage.module.css"
// import { FileText } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import useTraineeProfile from "../../../../hooks/TraineeHooks/useTraineeProfile.js";

import {
FileText
} from 'lucide-react';
export default function ProfilePage() {
    const {t}=useTranslation('trainee');
    const { profile, loading, error } = useTraineeProfile();
    if (loading) {
        return <p>Loading profile...</p>;
    }

    if (error) {
        return <p>Failed to load profile: {error}</p>;
    }

    if (!profile) {
        return <p>Profile not found.</p>;
    }
    
    const personal = profile.personal_information;
    const contact = profile.contact_information;
    const education = profile.educational_information;
    const attachments = profile.attachments;

    return (
        <main className={styles["page"]}>
            <header className={styles["image-label"]}>
                <Image />
                <TitleField
                    title={personal.name}
                    subTitle={personal.national_id_number ? `#${personal.national_id_number}` : "—"}
                />
            </header>
            <section className={styles["profile"]}>
                <BlocArea title={t("profile.personal")}>
                    <TextField lable={ t("profile.email")} value={contact.email ?? "—"} />
                    <TextField lable={t("profile.phone")} value={contact.phone_number ?? "—"} />
                    <TextField lable={t("profile.birthDate")} value={personal.birth_date ?? "—"} />
                    <TextField lable={t("profile.city")} value={contact.city ?? "—"} />
                    <TextField
                        lable={t("profile.address")}
                        value={contact.home_address ?? "—"}
                    />
                </BlocArea>
                <BlocArea title={t("profile.education")}>
                    <TextField
                        lable={t("profile.university")}
                        value={education.university ?? "—"}
                    />
                    <TextField
                        lable={t("profile.field")}
                        value={education.field_of_study ?? "—"}
                    />
                    <TextField
                        lable={t("profile.academicMajor")}
                        value={education.academic_major ?? "—"}
                    />
                    <TextField lable={t("profile.yearOfStudy")} value={education.year_of_study ?? "—"} />
                    <TextField lable={t("profile.gpa")} value={education.gpa ?? "—"} />
                </BlocArea>
                <BlocArea title={t("profile.skills")}>
                    <TextField
                        lable={t("profile.technicalSkills")}
                        value={
                            Array.isArray(education.technical_skills)
                                ? education.technical_skills.join(", ")
                                : education.technical_skills ?? "—"
                        }
                    />
                </BlocArea>
                {/* <BlocArea title={t("profile.otherInformation")}>
                    <TextField lable={t("profile.commitment")} value={t("profile.yes")} />
                    <TextField lable={t("profile.previousTraining")} value={t("profile.no")} />
                    <TextField
                        lable={t("profile.howDidYouHearAboutUs")}
                        value={t("profile.universityWorkshop")}
                    />
                </BlocArea> */}
                {/* <BlocArea title={t("profile.attachments")}>
                    <Button variant="soft" icon={FileText} children={attachments.cv ?? "—"}/>
                    <Button variant="soft" icon={FileText} children={attachments.national_id ?? "—"}/>
                    <Button variant="soft" icon={FileText} children={attachments.academic_qualification ?? "—"}/>
                </BlocArea> */}
                <BlocArea title={t("profile.attachments")}>

                    <a href={attachments.cv} target="_blank" rel="noopener noreferrer">
                        {t("profile.cv")}
                    </a>

                    <a href={attachments.national_id} target="_blank" rel="noopener noreferrer">
                        {t("profile.idAttachment")}
                    </a>

                    <a href={attachments.academic_qualification} target="_blank" rel="noopener noreferrer">
                        {t("profile.academicCertification")}
                    </a>

                </BlocArea>
            </section>
        </main>
    );
}