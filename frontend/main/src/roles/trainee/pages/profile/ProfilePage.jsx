import { changePassword } from "../../../../Api/Trainee/profileApi.js";
import Image from "../../../../components/ui/Image.jsx";
import TitleField from "../../../../components/ui/Title_Field.jsx";
import TextField from "../../../../components/ui/Text_Field.jsx";
import BlocArea from "../../../../components/ui/Bloc_Area.jsx";
import Button from "../../../../components/ui/Button.jsx"
import styles from "./ProfilePage.module.css"
import { useTranslation } from 'react-i18next';

import {
FileText
} from 'lucide-react';
export default function ProfilePage() {
    const {t}=useTranslation('trainee')
    return (
        <main className={styles["page"]}>
            <header className={styles["image-label"]}>
                <Image />
                <TitleField
                    title="Fadi Al Ajrodi"
                    subTitle="#052636584984"
                />
            </header>
            <section className={styles["profile"]}>
                <BlocArea title={t("profile.personal")}>
                    <TextField lable={ t("profile.email")} value="example@example.com" />
                    <TextField lable={t("profile.phone")} value="+963 994856354" />
                    <TextField lable={t("profile.birthDate")} value="10-5-2002" />
                    <TextField lable={t("profile.city")} value="Damascus" />
                    <TextField
                        lable={t("profile.address")}
                        value="Al Mazzeh, The Highway"
                    />
                </BlocArea>
                <BlocArea title={t("profile.education")}>
                    <TextField
                        lable={t("profile.university")}
                        value="Damascus University"
                    />
                    <TextField
                        lable={t("profile.field")}
                        value="Information Technology"
                    />
                    <TextField
                        lable={t("profile.academicMajor")}
                        value="Software Engineering"
                    />
                    <TextField lable={t("profile.yearOfStudy")} value="2014" />
                    <TextField lable={t("profile.gpa")} value="4.2" />
                </BlocArea>
                <BlocArea title={t("profile.skills")}>
                    <TextField
                        lable={t("profile.technicalSkills")}
                        value="React, Jira, ASP.NET, Colab"
                    />
                </BlocArea>
                <BlocArea title={t("profile.otherInformation")}>
                    <TextField lable={t("profile.commitment")} value={t("profile.yes")} />
                    <TextField lable={t("profile.previousTraining")} value={t("profile.no")} />
                    <TextField
                        lable={t("profile.howDidYouHearAboutUs")}
                        value={t("profile.universityWorkshop")}
                    />
                </BlocArea>
                <BlocArea title={t("profile.attachments")}>
                    <Button variant="soft" icon={FileText} children={t("profile.cv")}/>
                    <Button variant="soft" icon={FileText} children={t("profile.idAttachment")}/>
                    <Button variant="soft" icon={FileText} children={t("profile.academicCertification")}/>
                </BlocArea>
            </section>
        </main>
    );
}